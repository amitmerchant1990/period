<?php

namespace Spatie\Period;

use DateInterval;
use DateTimeImmutable;

class Period
{
    /** @var \DateTimeImmutable */
    protected $start;

    /** @var \DateTimeImmutable */
    protected $end;

    public function __construct(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        if ($start > $end) {
            throw InvalidPeriod::endBeforeStart($start, $end);
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function make($start, $end, string $format = 'Y-m-d H:i:s'): Period
    {
        return new self(
            DateTimeImmutable::createFromFormat($format, $start . ' 00:00:00'),
            DateTimeImmutable::createFromFormat($format, $end . ' 00:00:00')
        );
    }

    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    public function length(): int
    {
        return $this->start->diff($this->end)->days + 1;
    }

    public function overlapsWith(Period $period): bool
    {
        return $this->start <= $period->end
            && $period->start <= $this->end;
    }

    public function touches(Period $period): bool
    {
        return $this->end->diff($period->start)->days <= 1
            || $this->start->diff($period->end)->days <= 1;
    }

    public function startsAfterOrAt(DateTimeImmutable $date): bool
    {
        return $this->start >= $date;
    }

    public function endsAfterOrAt(DateTimeImmutable $date): bool
    {
        return $this->end >= $date;
    }

    public function startsBeforeOrAt(DateTimeImmutable $date): bool
    {
        return $this->start <= $date;
    }

    public function endsBeforeOrAt(DateTimeImmutable $date): bool
    {
        return $this->end <= $date;
    }

    public function equals(Period $period): bool
    {
        return $period->start->getTimestamp() === $this->start->getTimestamp()
            && $period->end->getTimestamp() === $this->end->getTimestamp();
    }

    public function gap(Period $period): ?Period
    {
        if (
            $this->overlapsWith($period)
            || $this->touches($period)
        ) {
            return null;
        }

        if ($this->start >= $period->end) {
            return new Period(
                $period->end->add(new DateInterval('P1D')),
                $this->start->sub(new DateInterval('P1D'))
            );
        }

        return new Period(
            $this->end->add(new DateInterval('P1D')),
            $period->start->sub(new DateInterval('P1D'))
        );
    }

    public function overlapSingle(Period $period): ?Period
    {
        $start = $this->start > $period->start
            ? $this->start
            : $period->start;

        $end = $this->end < $period->end
            ? $this->end
            : $period->end;

        if ($start > $end) {
            return null;
        }

        return new Period($start, $end);
    }

    public function overlap(Period ...$periods): PeriodCollection
    {
        $overlapCollection = new PeriodCollection();

        foreach ($periods as $period) {
            $overlapCollection[] = $this->overlapSingle($period);
        }

        return $overlapCollection;
    }

    public function overlapAll(Period ...$periods): Period
    {
        $overlap = clone $this;

        if (! count($periods)) {
            return $overlap;
        }

        foreach ($periods as $period) {
            $overlap = $overlap->overlapSingle($period);
        }

        return $overlap;
    }

    public function diffSingle(Period $period): PeriodCollection
    {
        $periodCollection = new PeriodCollection();

        if (! $this->overlapsWith($period)) {
            $periodCollection[] = clone $this;
            $periodCollection[] = clone $period;

            return $periodCollection;
        }

        $overlap = $this->overlapSingle($period);

        $start = $this->start < $period->start
            ? $this->start
            : $period->start;

        $end = $this->end > $period->end
            ? $this->end
            : $period->end;

        if ($overlap->start > $start) {
            $periodCollection[] = new Period(
                $start,
                $overlap->start->sub(new DateInterval('P1D'))
            );
        }

        if ($overlap->end < $end) {
            $periodCollection[] = new Period(
                $overlap->end->add(new DateInterval('P1D')),
                $end
            );
        }

        return $periodCollection;
    }

    public function diff(Period ...$periods): PeriodCollection
    {
        if (count($periods) === 1) {
            $collection = new PeriodCollection();

            if (! $this->overlapsWith($periods[0])) {
                $collection[] = $this->gap($periods[0]);
            }

            return $collection;
        }

        $diffs = [];

        foreach ($periods as $period) {
            $diffs[] = $this->diffSingle($period);
        }

        $collection = (new PeriodCollection($this))->overlap(...$diffs);

        return $collection;
    }
}