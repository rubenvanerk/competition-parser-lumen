<?php

namespace App\Http\Controllers;

use App\CompetitionConfig;
use App\Country;
use App\Http\Requests\StoreCompetitionConfigRequest;
use App\Services\Parsers\Parser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ParseError;

class CompetitionConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $competitions = CompetitionConfig::with('country:id,name')->paginate(15);
        return view('competition.index', ['competitions' => $competitions]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = [
            'countries' => Country::all(),
        ];
        return view('competition.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCompetitionConfigRequest $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(StoreCompetitionConfigRequest $request)
    {
        $competition = CompetitionConfig::create($request->validated());
        $competition->addMediaFromRequest('file')->toMediaCollection('results_file');
        return redirect('/');
    }

    /**
     * Display the specified resource.
     *
     * @param \App\CompetitionConfig $competition
     *
     * @return \Illuminate\Http\Response
     */
    public function show(CompetitionConfig $competition)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\CompetitionConfig $competition
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|View
     */
    public function edit(CompetitionConfig $competition)
    {
        return view('competition.edit', ['competition' => $competition, 'countries' => Country::all()]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\CompetitionConfig $competition
     *
     * @return \Illuminate\Http\Response
     */
    public function update(StoreCompetitionConfigRequest $request, CompetitionConfig $competition)
    {
        $competition->fill($request->validated());
        $competition->save();

        if ($request->hasFile('file')) {
            $competition->getMedia('results_file')->each->delete();
            $competition->addMediaFromRequest('file')->toMediaCollection('results_file');
        }

        return redirect()->route('competitions.edit', ['competition' => $competition]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\CompetitionConfig $competition
     *
     * @throws Exception
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(CompetitionConfig $competition)
    {
        $competition->delete();
        return redirect()->route('competitions.index');
    }

    /**
     * @param Request $request
     * @param \App\CompetitionConfig $competition
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|View
     */
    public function parse(Request $request, CompetitionConfig $competition)
    {
        if ($request->method() === 'PUT') {
            $this->saveConfigFromRequest($request, $competition);
            $action = $request->input('action');
            switch ($action) {
                case 'dry_run':
                    return redirect()->route('competitions.dry_run', ['competition' => $competition]);
                case 'save_to_database':
                    return redirect()->route('save_database', ['competition' => $competition, 'connection' => $action]);
                default:
                    return redirect()->route('competitions.parse', ['competition' => $competition]);
            }
        }

        $competitionParser = Parser::getInstance($competition);

        try {
            $rawData = $competitionParser->getRawData();
        } catch (Exception $exception) {
            $rawData = $exception->getMessage();
        }

        $data = [
            'file' => '',
            'competition' => $competition,
            'rawData' => $rawData,
            'rawDataText' => $competitionParser->getRawData(true),
            'fileExtension' => $competitionParser->getFileExtension(),
            'config' => $competitionParser->config,
            'databases' => config('database.connections'),
        ];

        return view('competition.parse', $data);
    }

    private function saveConfigFromRequest(Request $request, CompetitionConfig $competition): void
    {
        $competitionParser = Parser::getInstance($competition);
        $config = $competitionParser->config;

        foreach ($request->all()['data'] as $name => $value) {
            if (Str::endsWith($name, '_custom')) {
                continue;
            }
            if ($value === 'custom') {
                $value = $request->input('data')[$name . '_custom'];
            }
            $config->{$name} = (string)$value;
        }

        $config->save();
    }

    public function dryRun(CompetitionConfig $competition): View
    {
        $competitionParser = Parser::getInstance($competition);
        try {
            $parsedCompetition = $competitionParser->getParsedCompetition();
        } catch (ParseError $error) {
            return view('error', ['error' => $error->getMessage(), 'competition' => $competition]);
        }
        return view('dry_run', ['parsedCompetition' => $parsedCompetition, 'competition' => $competition]);
    }

    public function saveToDatabase(CompetitionConfig $competition): View
    {
        $competitionParser = Parser::getInstance($competition);
        $parsedCompetition = $competitionParser->getParsedCompetition();
        DB::transaction(function () use ($parsedCompetition) {
            $parsedCompetition->saveToDatabase();
        });
        return view('save_to_database', ['parsedCompetition' => $parsedCompetition, 'competition' => $competition]);
    }
}