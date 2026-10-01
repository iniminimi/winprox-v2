<?php

namespace App\Enums;

/**
 * Uitkomst van een PIN-poging op het klokscherm (no-phone fallback).
 */
enum ClockDisplayPinStatus: string
{
    case ClockedIn = 'clocked_in';
    case ClockedOut = 'clocked_out';
    case InvalidPin = 'invalid_pin';
    case WorkerLocked = 'worker_locked';
    case WorkerNotFound = 'worker_not_found';
}
