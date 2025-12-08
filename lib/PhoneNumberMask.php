<?php

/**
 * SPDX-FileCopyrightText: 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway;

class PhoneNumberMask {

        /**
         * convert 123456789 to ******789
         */
        public static function maskNumber(string $number): string {
                $length = strlen($number);
                $visible = substr($number, -3);
                $hiddenLength = max(0, $length - strlen($visible));

                return str_repeat('*', $hiddenLength) . $visible;
        }
}
