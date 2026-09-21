<?php

namespace App\Service;

class RutService
{
    /**
     * Valida un RUT chileno usando el algoritmo módulo 11.
     * Acepta formatos: 12345678-9, 12.345.678-9, 123456789
     */
    public function validate(string $rut): bool
    {
        $rut = $this->clean($rut);
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $body = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));
        
        if (!ctype_digit($body)) {
            return false;
        }
        
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int)$body[$i] * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }
        
        $remainder = 11 - ($sum % 11);
        
        $expectedDv = match($remainder) {
            11 => '0',
            10 => 'K',
            default => (string)$remainder,
        };
        
        return $dv === $expectedDv;
    }
    
    /**
     * Calcula el dígito verificador (módulo 11) para el cuerpo del RUT.
     */
    public function calculateDv(string $body): string
    {
        $body = preg_replace('/[^0-9]/', '', $body) ?? '';
        $sum = 0;
        $multiplier = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }
        $remainder = 11 - ($sum % 11);

        return match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };
    }

    /**
     * Limpia el RUT (quita puntos y guiones)
     */
    public function clean(string $rut): string
    {
        return strtoupper(str_replace(['.', '-'], '', trim($rut)));
    }
    
    /**
     * Formatea el RUT al formato estándar: 76.123.456-7
     */
    public function format(string $rut): string
    {
        $clean = $this->clean($rut);
        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);
        
        $formattedBody = number_format((int)$body, 0, '', '.');
        
        return $formattedBody . '-' . $dv;
    }
}