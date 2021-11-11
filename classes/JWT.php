<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

require_once DFTDAPI_PATH . 'jwt_secret.php';

class DFTDAPI_JWT {
    public static function getAuthenticatedUser() {
        $token = self::get();

        if($token != '') {
            $parsedToken = self::parse($token);
            if($parsedToken) {
                return $parsedToken->sub;
            }
        }

        return false;
    }

    public static function generate($payload) {
        $headers = ['alg' => 'HS256', 'typ' => 'JWT'];

        $b64Header = self::b64(json_encode($headers));
        $b64Payload = self::b64(json_encode($payload));
        
        $signature = hash_hmac('SHA256', "$b64Header.$b64Payload", DFTDAPI_JWT_SECRET, true);
        $b64Signature = self::b64($signature);
                
        return "$b64Header.$b64Payload.$b64Signature";
    }

    public static function get() {
        $header = '';

        if(isset($_SERVER['Authorization'])) {
            $header = trim($_SERVER['Authorization']);
        } else if(isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else if(function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization).
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if(isset($requestHeaders['Authorization'])) {
                $header = trim($requestHeaders['Authorization']);
            }
        }
        
        if($header != '' && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function parse($jwt, $checkExpired = true) {
        $tokenParts = explode('.', $jwt);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $providedSignature = $tokenParts[2];
        $parsed = json_decode($payload);

        $b64Header = self::b64($header);
        $b64Payload = self::b64($payload);
        $b64Signature = self::b64(hash_hmac('SHA256', $b64Header . '.' . $b64Payload, DFTDAPI_JWT_SECRET, true));
    
        // If token expired or signatures don't match, return false, else return parsed data.
        if($b64Signature !== $providedSignature) {
            return 0;
        } else if($checkExpired && ($parsed->exp - time()) < 0) {
            return -1;
        } else {
            return $parsed;
        }
    }

    private static function b64($string) {
        return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
    }
}