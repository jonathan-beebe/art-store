<?php

declare(strict_types=1);

function moment(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when);
}
