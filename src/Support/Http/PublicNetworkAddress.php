<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Http;

/** Conservative egress policy: public unicast only, including IPv4-mapped IPv6 denial. */
final class PublicNetworkAddress
{
    public static function allows(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (str_contains($ip, ':')) {
            return self::contains('2000::/3', $ip)
                && ! self::contains('2001::/23', $ip)
                && ! self::contains('2001:db8::/32', $ip)
                && ! self::contains('2002::/16', $ip)
                && ! self::contains('3fff::/20', $ip);
        }

        foreach (['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
            if (self::contains($cidr, $ip)) {
                return false;
            }
        }

        return true;
    }

    private static function contains(string $cidr, string $ip): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $address = inet_pton($ip);
        $prefix = inet_pton($network);

        if ($address === false || $prefix === false || strlen($address) !== strlen($prefix)) {
            return false;
        }

        $bytes = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;

        return substr($address, 0, $bytes) === substr($prefix, 0, $bytes)
            && ($remainder === 0 || (ord($address[$bytes]) & (0xFF << (8 - $remainder))) === (ord($prefix[$bytes]) & (0xFF << (8 - $remainder))));
    }
}
