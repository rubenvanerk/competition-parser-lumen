@extends('layouts.app')

@section('content')
    <h2>Parsing {{ $competition->name }}</h2>
    <a class="btn btn-primary" href="{{ $competitionConfig->getFirstMediaUrl('results_file') }}"
       target="_blank">
        View source file
    </a>
    <a class="btn btn-secondary" href="{{ route('competitions.edit', ['competition' => $competition]) }}">
        Edit
    </a>

    <hr class="my-3">

    <div class="row">
        <div class="col-lg-5">
            <form method="post">
                @csrf
                @method('PUT')

                @foreach($config->config as $name => $value)
                    @php($parentField = '')
                    @include('partials.field')
                @endforeach

                <label class="font-weight-bolder mt-2">Action:</label>

                <div class="form-check">
                    <input type="radio" name="action" id="save_config" value="save_config" checked
                           class="form-check-input">
                    <label for="save_config" class="form-check-label">Save config</label><br>
                </div>

                <div class="form-check">
                    <input type="radio" name="action" id="dry_run" value="dry_run" class="form-check-input">
                    <label for="dry_run" class="form-check-label">Dry run</label>
                </div>

                <div class="form-check">
                    <input type="radio" name="action" id="save_to_database" value="save_to_database"
                           class="form-check-input">
                    <label for="save_to_database" class="form-check-label">Save to database</label>
                </div>

                <button type="submit" class="btn btn-primary my-3">Save</button>

            </form>
        </div>

        <div class="col-lg-7">
            <h2>Raw data</h2>

            <a href="" id="firstMatch">Go to first match</a>

            <div class="raw-data">
                @if($fileExtension === 'csv' || $config->{'as_csv.as_csv'})
                    <details>
                        <summary class="py-2">Table</summary>
                        <div class="content">
                            {!! $rawData !!}
                        </div>
                    </details>
                    <details>
                        <summary class="py-2">Text</summary>
                        <div class="content">
                    <pre class="overflow-scroll">
                        {{ $rawDataText }}
                    </pre>
                        </div>
                    </details>
                @else
                    <pre class="overflow-auto">
                        {{ $rawData }}
                    </pre>
                @endif
            </div>

        </div>
    </div>
@endsection
