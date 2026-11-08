<?php

if (! function_exists('angka')) {
    function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}

if (! function_exists('rupiah')) {
    function rupiah(int $amount): string
    {
        return 'Rp'.angka($amount);
    }
}
