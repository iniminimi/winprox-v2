<?php

/**
 * Lokaal flashen van een klokscherm aan USB. De productieserver heeft die poort niet.
 * Paden volgen .cursor/rules/clock-flash.mdc. Overschrijf ze via .env als de schijf anders ligt.
 */
return [
    'usb_flash' => [
        'python' => env('OPS_USB_FLASH_PYTHON', 'C:\\Users\\domin\\AppData\\Local\\Programs\\Python\\Python313\\python.exe'),
        'esptool' => env('OPS_USB_FLASH_ESPTOOL', 'C:\\Users\\domin\\.platformio\\packages\\tool-esptoolpy\\esptool.py'),
        'bootloader' => env('OPS_USB_FLASH_BOOTLOADER', 'C:\\werkmap\\winprox-clock\\.pio\\build\\indicator\\bootloader.bin'),
        'partitions' => env('OPS_USB_FLASH_PARTITIONS', 'C:\\werkmap\\winprox-clock\\.pio\\build\\indicator\\partitions.bin'),
        'firmware' => env('OPS_USB_FLASH_FIRMWARE', 'C:\\werkmap\\winprox-clock\\.pio\\build\\indicator\\firmware.bin'),
        'vid' => 0x1A86,
        'pid' => 0x7523,
    ],
];
