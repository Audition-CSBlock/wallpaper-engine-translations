<?php
declare(strict_types=1);

namespace WPE\Localization;

use Seld\JsonLint\JsonParser;

class LanguageFile
{
    private $filePath;
    private $jsonValues;
    private $missingKeys;
    private $violations = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function getName(): string
    {
        return basename($this->filePath);
    }

    public function getFileGroup(): string
    {
        $groupNamePos = strpos($this->getName(), '_');
        if ($groupNamePos === false) {
            throw new \InvalidArgumentException('Invalid file name: '.$this->filePath);
        }

        return substr($this->getName(), 0, $groupNamePos);
    }

    public function getJsonData(): array {
        if ($this->jsonValues === null) {
            $this->parseJson();
        }
        return $this->jsonValues;
    }

    public function getFileCompletion(LanguageFile $baseFile): float
    {
        return 100 - floor(count($this->getMissingKeys($baseFile)) / count($baseFile->getJsonData()) * 100);
    }

    public function getMissingKeys( LanguageFile $baseFile): array {
        if ($this->missingKeys !== null) {
            return $this->missingKeys;
        }
        $this->missingKeys = [];
        foreach ($baseFile->getJsonData() as $baseKey => $baseString) {
            $found = false;
            foreach ($this->getJsonData() as $jsonKey => $localizedString) {
                if ($baseKey === $jsonKey) {
                    if ($localizedString != '') {
                        $found = true;
                        $this->findStringViolations($baseString, $localizedString, $jsonKey);
                    }
                    break;
                }
            }
            if ($found === false) {
                $this->missingKeys[] = $baseKey;
            }
        }
        return $this->missingKeys;
    }

    private function findStringViolations(string $baseString, string $localizedString, string $jsonKey): void {
        if (preg_match_all('/{{(.*?)}}/', $baseString, $baseVariables)) {
            preg_match_all('/{{(.*?)}}/', $localizedString, $localizedVariables);
            foreach ($baseVariables[0] as $baseVariable) {
                $found = false;
                foreach ($localizedVariables[0] as $localizedVariable) {
                    if ($baseVariable === $localizedVariable) {
                        $found = true;
                    }
                }
                if ($found === false) {
                    $this->addViolation('Key ' . $jsonKey . ' was translated but is missing variable ' . $baseVariable);
                }
            }
        }
    }

    public function addViolation(string $errorMessage): void
    {
        $this->violations[] = $errorMessage;
    }

    public function hasErrors(): bool
    {
        return !empty($this->violations);
    }

    public function getViolations(): array {
        return $this->violations;
    }

    private function parseJson(): void
    {
        $content = file_get_contents($this->filePath);
        $jsonParser = new JsonParser();
        if ($content == '') {
            throw new \InvalidArgumentException($this->filePath.' is not readable or empty.');
        }
        if ($error = $jsonParser->lint($content, JsonParser::DETECT_KEY_CONFLICTS)) {
            die($this->filePath.': '.$error->getMessage());

        }
        $this->jsonValues = $jsonParser->parse($content, JsonParser::PARSE_TO_ASSOC);
    }
}