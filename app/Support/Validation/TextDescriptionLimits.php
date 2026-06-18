<?php

namespace App\Support\Validation;

final class TextDescriptionLimits
{
    /** Max. lengte voor door gebruikers ingevulde leestekst (melding, taak, update, mededeling, …). */
    public const MAX = 500;

    /**
     * Max. lengte voor machinevertalingen (brontekst max. 500; vertalingen mogen langer uitvallen).
     */
    public const TRANSLATION_MAX = 1500;
}
