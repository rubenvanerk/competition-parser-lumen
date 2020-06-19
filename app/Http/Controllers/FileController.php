<?php

namespace App\Http\Controllers;

use App\Services\Parsers\Parser;
use Carbon\Carbon;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function browse(string $path = null): \Illuminate\View\View
    {
        $breadcrumbs = [];
        $breadcrumbs[] = [
            'path' => '',
            'name' => 'root'
        ];
        $previousBreadcrumb = '';
        foreach (explode('/', $path) as $directory) {
            $breadcrumbs[] = [
                'path' => $previousBreadcrumb .= $directory . '/',
                'name' => $directory
            ];
        }

        $files = Storage::files($path);
        $filesWithoutYaml = preg_grep('/^.*(?<!\.yaml)$/', $files);

        $data = [
            'directories' => Storage::directories($path),
            'files' => $filesWithoutYaml,
            'path' => $path,
            'breadcrumbs' => $breadcrumbs
        ];

        return view('browse', $data);
    }

    public function upload(Request $request): \Illuminate\Http\RedirectResponse
    {
        $fileName = $request->input('filename');
        $file = $request->file('results');
        $date = new Carbon($request->input('date'));
        $path = $date->year . DIRECTORY_SEPARATOR . $date->month;
        $file->storeAs($path, $fileName);

        $competitionParser = Parser::getInstance($path . DIRECTORY_SEPARATOR . $fileName);
        $config = $competitionParser->config;
        $config->{'info.date'} = $request->input('date');
        $config->save();

        return redirect()->route('config', ['file' => $path . '/' . $fileName]);
    }

    public function config(string $file): \Illuminate\View\View
    {
        $competitionParser = Parser::getInstance($file);

        // s3 url
        // Storage::temporaryUrl($file, Carbon::now()->addMinutes(5));
        $data = [
            'file' => $file,
            'temporaryUrl' => Storage::url($file),
            'rawData' => $competitionParser->getRawData(),
            'config' => $competitionParser->config,
            'databases' => config('database.connections')
        ];

        return view('config', $data);
    }

    public function saveConfig(Request $request, File $file): \Illuminate\Http\RedirectResponse
    {
        $this->saveConfigFromRequest($request, $file);
        $action = $request->input('action');
        switch ($action) {
            case 'dry_run':
                return redirect()->route('dry_run', ['file' => $file]);
            case 'save_config':
                return redirect()->route('config', ['file' => $file]);
            default:
                if (!array_key_exists($action, config('database.connections'))) {
                    return redirect()->route('config', ['file' => $file]);
                }
                return redirect()->route('save_database', ['file' => $file, 'connection' => $action]);
        }
    }

    public function dryRun(string $file): \Illuminate\View\View
    {
        $competitionParser = Parser::getInstance($file);
        $parsedCompetition = $competitionParser->getParsedCompetition();
        return view('dry_run', ['competition' => $parsedCompetition, 'file' => $file]);
    }

    public function saveToDatabase(string $file, string $connection): \Illuminate\View\View
    {
        Config::set('database.default', $connection);
        $competitionParser = Parser::getInstance($file);
        $parsedCompetition = $competitionParser->getParsedCompetition();
        \DB::transaction(function () use ($parsedCompetition) {
            $parsedCompetition->saveToDatabase();
        });
        return view('save_to_database', ['competition' => $parsedCompetition, 'file' => $file]);
    }


    private function saveConfigFromRequest(Request $request, File $file): void
    {
        $competitionParser = Parser::getInstance($file);
        $config = $competitionParser->config;

        foreach ($request->all()['data'] as $name => $value) {
            if (Str::endsWith($name, '_custom')) {
                continue;
            }
            if ($value === 'custom') {
                $value = $request->input('data')[$name . '_custom'];
            }
            $config->{$name} = $value;
        }

        $config->save();
    }
}
