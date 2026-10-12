<?php

namespace App\Enums;

enum BackofficePunch: string
{
    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';
    case StartBreak = 'start_break';
    case EndBreak = 'end_break';
}
