<?php
// Polyfill para funções mbstring quando mbstring não está disponível
if (!extension_loaded('mbstring')) {
    if (!function_exists('mb_split')) {
        function mb_split($pattern, $string, $limit = -1) {
            // Lidar com padrões que já contém escape
            if (strpos($pattern, '\\') !== false) {
                // Se já tem escape, adicionar delimitadores
                $pattern = '/' . str_replace('/', '\/', $pattern) . '/';
            } elseif (!preg_match('/^\/.*\/[gimsu]*$/', $pattern)) {
                // Se não é regex completa, fazer escape e adicionar delimitadores
                $pattern = '/' . preg_quote($pattern, '/') . '/';
            }
            return preg_split($pattern, $string, $limit);
        }
    }
    if (!function_exists('mb_strlen')) {
        function mb_strlen($string, $encoding = 'UTF-8') {
            return strlen($string);
        }
    }
    if (!function_exists('mb_substr')) {
        function mb_substr($string, $start, $length = null, $encoding = 'UTF-8') {
            return substr($string, $start, $length);
        }
    }
    if (!function_exists('mb_strtolower')) {
        function mb_strtolower($string, $encoding = 'UTF-8') {
            return strtolower($string);
        }
    }
    if (!function_exists('mb_strtoupper')) {
        function mb_strtoupper($string, $encoding = 'UTF-8') {
            return strtoupper($string);
        }
    }
}
