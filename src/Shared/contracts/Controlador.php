<?php
interface Controlador
{
    /** @param string[] $partes Segmentos de la URL: ['donantes', '5'] */
    public function control(string $metodo, array $partes): void;
}