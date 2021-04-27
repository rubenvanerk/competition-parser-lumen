<?php

namespace App\Services\Parsers;

use App\CompetitionConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ParseError;
use Symfony\Component\Yaml\Yaml;

class ParserConfig
{
    /** @var CompetitionConfig */
    private $competition;
    /** @var string */
    private $fileName;
    /** @var array */
    private $config;
    /** @var array */
    public $template;

    private const FILE_EXTENSION_TEMPLATE_MAPPINGS = [
        'pdf' => 'text_template.yaml',
        'txt' => 'text_template.yaml',
        'lxf' => 'lenex_template.yaml',
        'csv' => 'csv_template.yaml',
    ];

    public function __construct(CompetitionConfig $competition)
    {
        $this->competition = $competition;
        $this->fileName = $competition->getFirstMedia('results_file')->file_name . '.yaml';
        $this->loadConfig();
        $templateContent = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'parser_template.yaml');
        if (!$templateContent) {
            throw new ParseError('Could not find parser_template.yaml');
        }
        $this->template = Yaml::parse($templateContent) ?: [];
    }

    private function loadConfig(): void
    {
        $fileType = $this->competition->getFileType();
        $emptyConfig = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . self::FILE_EXTENSION_TEMPLATE_MAPPINGS[$fileType]);

        if (!$emptyConfig) {
            throw new ParseError(sprintf('Could not find empty template %s', self::FILE_EXTENSION_TEMPLATE_MAPPINGS[$fileType]));
        }

        if (!$this->competition->parser_config) {
            $this->competition->parser_config = Yaml::parse($emptyConfig);
            $this->competition->save();
        }

        $this->config = array_replace_recursive(Yaml::parse($emptyConfig), $this->competition->parser_config);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        return Arr::get($this->config, $name);
    }

    public function __set(string $name, string $value): void
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }
        Arr::set($this->config, $name, $value);
    }

    public function __isset(string $name): bool
    {
        return isset($this->config[$name]);
    }

    public function save(): void
    {
        $this->competition->parser_config = $this->config;
        $this->competition->save();
    }

    public function remove(string $name): void
    {
        Arr::forget($this->template, $name);
    }

    public function getType(string $name): ?string
    {
        return Arr::get($this->template, $name . '.type');
    }

    public function getLabel(string $name): ?string
    {
        $label = Arr::get($this->template, $name . '.label');
        if (!$label) {
            $name = Str::afterLast($name, '.');
            $label = ucfirst(str_replace('_', ' ', $name));
        }
        return $label;
    }

    public function getOptions(string $name): ?array
    {
        $result = Arr::get($this->config, $name . '.options');
        if (!is_array($result)) {
            $result = Arr::get($this->template, $name . '.options');
        }
        return $result;
    }

    public function getValueIfCustom(string $name): ?string
    {
        $value = $this->$name;
        return $this->valueIsCustom($name) ? $value : null;
    }

    public function valueIsCustom(string $name): bool
    {
        $value = $this->$name;
        $options = $this->getOptions($name);
        if (!$options) {
            throw new ParseError(sprintf('Could not find options for %s', $name));
        }
        $options = array_keys($options);
        return !empty($value) && !is_array($value) && !in_array($value, $options, false);
    }

    public function allowCustom(string $name): bool
    {
        $allowCustom = Arr::get($this->template, $name . '.custom');
        return is_null($allowCustom) || $allowCustom;
    }

    public function getTextAreaAsArray(string $name): array
    {
        $fieldValue = $this->{$name};
        $lines = explode("\n", $fieldValue);
        $lines = preg_replace("/\r$/", '', $lines) ?? [];

        $result = [];
        foreach ($lines as $line) {
            $keyValue = explode('>>', $line);
            $result[array_shift($keyValue)] = array_shift($keyValue);
        }
        return $result;
    }
}
