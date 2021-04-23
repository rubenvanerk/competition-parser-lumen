<?php

namespace App\Services\Parsers;

use App\CompetitionConfig;
use App\Services\Cleaners\Cleaner;
use App\Services\ParsedObjects\ParsedAthlete;
use App\Services\ParsedObjects\ParsedIndividualResult;
use App\Services\ParsedObjects\ParsedSplit;
use leonverschuren\Lenex\Model\Lenex;
use ParseError;

class LenexParser extends Parser
{
    /** @var Parser */
    private static $_instance;
    public static function getInstance(CompetitionConfig $competition): Parser
    {
        if (!(self::$_instance instanceof self)) {
            self::$_instance = new self($competition);
        }

        return self::$_instance;
    }

    public function getRawData(bool $returnText = false): string
    {
        // TODO: Implement getRawData() method.
        return '';
    }

    protected function parse(): void
    {
        $reader = new \leonverschuren\Lenex\Reader();
        $parser = new \leonverschuren\Lenex\Parser();
        $parsedLenex = $parser->parseResult($reader->read(storage_path('app' . DIRECTORY_SEPARATOR .$this->competition)));
        $eventMappings = $this->parseEvents($parsedLenex);

        foreach ($parsedLenex->getMeets() as $meet) {
            foreach ($meet->getClubs() as $club) {
                foreach ($club->getAthletes() as $lenexAthlete) {
                    if (!$lenexAthlete->getResults()) {
                        continue;
                    }
                    $athleteName = $lenexAthlete->getFirstName() . ($lenexAthlete->getNamePrefix() ? ' ' . $lenexAthlete->getNamePrefix() : '') . ' ' . $lenexAthlete->getLastName();
                    $gender = $lenexAthlete->getGender() === 'F' ? ParsedAthlete::FEMALE : ParsedAthlete::MALE;
                    $yearOfBirth = (int)$lenexAthlete->getBirthDate()->format('Y');

                    $parsedAthlete = new ParsedAthlete(
                        $athleteName,
                        $yearOfBirth,
                        $gender,
                        $lenexAthlete->getNation(),
                        $club->getName()
                    );

                    foreach ($lenexAthlete->getResults() as $lenexResult) {
                        $parsedSplits = [];
                        foreach ($lenexResult->getSplits() as $split) {
                            if ((int)$split->getDistance() % 50 !== 0) {
                                $parsedSplits = [];
                                break;
                            }
                            $parsedSplits[] = new ParsedSplit(
                                Cleaner::cleanTime($split->getSwimTime()),
                                $split->getDistance()
                            );
                        }

                        $parsedResult = new ParsedIndividualResult(
                            $lenexResult->getStatus() ? null : Cleaner::cleanTime($lenexResult->getSwimTime()),
                            $parsedAthlete,
                            0,
                            $lenexResult->getStatus() === 'DSQ',
                            $lenexResult->getStatus() === 'DNS',
                            $lenexResult->getStatus() === 'WDR',
                            null,
                            $lenexResult->getHeatId(),
                            $lenexResult->getLane(),
                            $lenexResult->getReactionTime() ? Cleaner::cleanTime($lenexResult->getReactionTime()) : null,
                            $parsedSplits
                        );
                        $parsedResult->eventId = $eventMappings[$lenexResult->getEventId()];
                        $this->parsedCompetition->results[] = $parsedResult;
                    }
                }
            }
        }
    }

    private function parseEvents(Lenex $parsedLenex): array
    {
        $eventMappings = [];

        foreach ($parsedLenex->getMeets() as $meet) {
            if ($meet->getCourse() !== 'LCM') {
                throw new ParseError(sprintf('Course type should be LCM %s', $meet->getCourse()));
            }
            foreach ($meet->getSessions() as $session) {
                foreach ($session->getEvents() as $event) {
                    $swimStyle = $event->getSwimStyle();
                    if (!($eventName = $swimStyle->getName())) {
                        $eventName = $swimStyle->getDistance() . 'm ' . $swimStyle->getStroke();
                    }
                    if ($this->config->{'events.event_rejector'}
                        && preg_match($this->config->{'events.event_rejector'}, $eventName) === 1) {
                        continue;
                    }

                    $eventId = $this->getEventIdFromLine($eventName);

                    $eventMappings[$event->getEventId()] = $eventId;
                }
            }
        }

        return $eventMappings;
    }

    private function getEventIdFromLine(string $line): int
    {
        foreach ($this->config->{'events.event_names'} as $eventId => $eventRegex) {
            if (preg_match($eventRegex, $line) === 1) {
                return $eventId;
            }
        }
        throw new ParseError(sprintf('Could not find event in line \'%s\'', $line));
    }
}
