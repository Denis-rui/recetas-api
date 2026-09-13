<?php

return [
    'registro_por_minuto' => (int) env('API_REGISTRO_POR_MINUTO', 5),
    'registro_por_hora' => (int) env('API_REGISTRO_POR_HORA', 20),
    'restablecer_por_minuto' => (int) env('API_RESTABLECER_POR_MINUTO', 10),
    'disponibilidad_por_minuto' => (int) env('API_DISPONIBILIDAD_POR_MINUTO', 30),
    'escrituras_por_minuto' => (int) env('API_ESCRITURAS_POR_MINUTO', 60),
    'imagenes_por_minuto' => (int) env('API_IMAGENES_POR_MINUTO', 10),
];
