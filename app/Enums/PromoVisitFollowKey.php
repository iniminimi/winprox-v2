<?php

namespace App\Enums;

enum PromoVisitFollowKey: string
{
    case Register = 'register';
    case Contact = 'contact';
    case Features = 'features';
    case Pricing = 'pricing';
    case Faq = 'faq';
    case About = 'about';

    public static function fromRouteName(?string $routeName): ?self
    {
        return match ($routeName) {
            'register' => self::Register,
            'contact.index' => self::Contact,
            'pricing' => self::Pricing,
            'faq.public' => self::Faq,
            'about' => self::About,
            'features.facility', 'features.time', 'features.esg', 'features.iot', 'features.qr' => self::Features,
            default => null,
        };
    }
}
