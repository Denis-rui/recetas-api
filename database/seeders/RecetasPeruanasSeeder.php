<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RecetasPeruanasSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('rol', 'administrador')->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'name' => 'Admin Chef',
                'email' => 'chef@recetas.com',
                'password' => bcrypt('password'),
                'rol' => 'administrador',
                'activo' => true,
            ]);
        }

        $recetas = [
            [
                'nombre' => 'Ceviche Clásico',
                'descripcion' => 'El plato de bandera del Perú, refrescante y delicioso.',
                'porciones' => 2,
                'tiempo_preparacion' => 20,
                'tips' => 'Asegúrate de que el pescado esté muy fresco y el limón recién exprimido.',
                'categoria' => 'Entradas',
                'ingredientes' => [
                    ['nombre' => 'Pescado fresco', 'cantidad' => 500, 'unidad' => 'gramos', 'notas' => 'Cortado en cubos'],
                    ['nombre' => 'Limón', 'cantidad' => 10, 'unidad' => 'unidades', 'notas' => 'Jugo recién exprimido'],
                    ['nombre' => 'Cebolla roja', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Corte pluma fino'],
                    ['nombre' => 'Ají limo', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Picado finamente sin venas'],
                    ['nombre' => 'Culantro fresco', 'cantidad' => 1, 'unidad' => 'cucharada', 'notas' => 'Picado'],
                    ['nombre' => 'Sal', 'cantidad' => 1, 'unidad' => 'cucharadita', 'notas' => 'Al gusto'],
                ],
                'pasos' => [
                    'Colocar el pescado en un bol de vidrio y sazonar con sal y ají limo.',
                    'Exprimir los limones sobre el pescado. Evitar exprimir hasta el final para no amargar.',
                    'Añadir el culantro picado y mezclar bien.',
                    'Colocar la cebolla cortada en pluma lavada previamente.',
                    'Dejar reposar 3-5 minutos y servir inmediatamente con camote y choclo.',
                ],
            ],
            [
                'nombre' => 'Lomo Saltado',
                'descripcion' => 'Una fusión peruano-china con carne de res, vegetales y salsa de soya.',
                'porciones' => 4,
                'tiempo_preparacion' => 30,
                'tips' => 'El secreto es el wok muy caliente para lograr el sabor ahumado.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Lomo fino o cuadril', 'cantidad' => 600, 'unidad' => 'gramos', 'notas' => 'En tiras gruesas'],
                    ['nombre' => 'Cebolla roja', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'Cortadas en gajos'],
                    ['nombre' => 'Tomate', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'En gajos sin semillas'],
                    ['nombre' => 'Ají amarillo', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'En tiras'],
                    ['nombre' => 'Sillao (salsa de soya)', 'cantidad' => 3, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Vinagre tinto', 'cantidad' => 2, 'unidad' => 'cucharadas', 'notas' => ''],
                ],
                'pasos' => [
                    'Sazonar la carne con sal y pimienta.',
                    'En un wok o sartén con aceite muy caliente, sellar la carne y reservar.',
                    'En la misma sartén, saltar la cebolla y el ají amarillo por un minuto.',
                    'Regresar la carne al wok, agregar vinagre y sillao. Flambear si es posible.',
                    'Añadir los tomates y culantro picado, apagar el fuego y mezclar. Servir con papas fritas y arroz.',
                ],
            ],
            [
                'nombre' => 'Ají de Gallina',
                'descripcion' => 'Un guiso cremoso y ligeramente picante a base de ají amarillo, pollo y pecanas.',
                'porciones' => 4,
                'tiempo_preparacion' => 45,
                'tips' => 'Puedes reemplazar el pan por galleta de soda.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Pechuga de pollo', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Entera'],
                    ['nombre' => 'Pasta de ají amarillo', 'cantidad' => 150, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Pan francés', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'O pan de molde'],
                    ['nombre' => 'Leche evaporada', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Cebolla roja', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Picada fina'],
                    ['nombre' => 'Pecanas picadas', 'cantidad' => 50, 'unidad' => 'gramos', 'notas' => ''],
                ],
                'pasos' => [
                    'Sancochar la pechuga de pollo. Reservar el caldo y deshilachar el pollo.',
                    'Remojar el pan en leche y caldo, luego licuar.',
                    'Hacer un aderezo con aceite, ajo y cebolla. Añadir la pasta de ají amarillo y cocinar.',
                    'Agregar el pan licuado y mover constantemente hasta que tome punto.',
                    'Añadir el pollo deshilachado, el resto de leche y pecanas. Servir con papas sancochadas.',
                ],
            ],
            [
                'nombre' => 'Causa Limeña',
                'descripcion' => 'Entrada fría de papa amarilla prensada con ají amarillo, rellena de pollo.',
                'porciones' => 6,
                'tiempo_preparacion' => 40,
                'tips' => 'Amasa la papa cuando aún esté tibia.',
                'categoria' => 'Entradas',
                'ingredientes' => [
                    ['nombre' => 'Papa amarilla', 'cantidad' => 1, 'unidad' => 'kg', 'notas' => 'Sancochada'],
                    ['nombre' => 'Pasta de ají amarillo', 'cantidad' => 3, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Limón', 'cantidad' => 3, 'unidad' => 'unidades', 'notas' => 'Jugo'],
                    ['nombre' => 'Pollo deshilachado', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Mayonesa', 'cantidad' => 4, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Palta (Aguacate)', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'En rodajas'],
                ],
                'pasos' => [
                    'Pelar las papas y prensarlas calientes.',
                    'Amasar la papa con ají amarillo, limón, aceite y sal hasta tener masa suave.',
                    'Mezclar el pollo con mayonesa.',
                    'En un molde, poner masa de papa, rodajas de palta y la mezcla de pollo.',
                    'Cubrir con masa de papa. Decorar con huevo duro.',
                ],
            ],
            [
                'nombre' => 'Papa a la Huancaína',
                'descripcion' => 'Rodajas de papa bañadas en salsa de queso y ají amarillo.',
                'porciones' => 5,
                'tiempo_preparacion' => 20,
                'tips' => 'Saltea los ajíes en aceite antes de licuar para mejor sabor.',
                'categoria' => 'Entradas',
                'ingredientes' => [
                    ['nombre' => 'Papa blanca o amarilla', 'cantidad' => 1, 'unidad' => 'kg', 'notas' => 'Sancochadas'],
                    ['nombre' => 'Ají amarillo', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => 'Sin venas'],
                    ['nombre' => 'Queso fresco', 'cantidad' => 300, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Leche evaporada', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Galletas de soda', 'cantidad' => 6, 'unidad' => 'unidades', 'notas' => ''],
                    ['nombre' => 'Aceite', 'cantidad' => 2, 'unidad' => 'cucharadas', 'notas' => ''],
                ],
                'pasos' => [
                    'Saltear los ajíes con un poco de ajo por 2 minutos.',
                    'Licuar los ajíes, el queso, la leche y el aceite.',
                    'Añadir galletas de soda hasta lograr textura cremosa.',
                    'Sazonar con sal al gusto.',
                    'Servir sobre lechuga y rodajas de papa. Decorar con huevo y aceituna.',
                ],
            ],
            [
                'nombre' => 'Arroz con Pollo',
                'descripcion' => 'Arroz verde clásico hecho a base de culantro, servido con pollo.',
                'porciones' => 6,
                'tiempo_preparacion' => 60,
                'tips' => 'El uso de chicha de jora eleva el sabor del arroz.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Piezas de pollo', 'cantidad' => 6, 'unidad' => 'unidades', 'notas' => ''],
                    ['nombre' => 'Arroz', 'cantidad' => 3, 'unidad' => 'tazas', 'notas' => ''],
                    ['nombre' => 'Culantro fresco', 'cantidad' => 1, 'unidad' => 'atado', 'notas' => 'Licuado'],
                    ['nombre' => 'Cerveza negra o chicha de jora', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Zanahoria', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'En cuadritos'],
                    ['nombre' => 'Alverjas', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                ],
                'pasos' => [
                    'Sellar las piezas de pollo salpimentadas y retirar.',
                    'Sofreír cebolla, ajo y ají amarillo en el mismo aceite.',
                    'Añadir culantro licuado y cocinar por 5 minutos.',
                    'Agregar cerveza, pollo y cocinar 15 minutos.',
                    'Retirar el pollo, añadir arroz, verduras y agua. Cocinar hasta granear.',
                ],
            ],
            [
                'nombre' => 'Anticuchos de Corazón',
                'descripcion' => 'Brochetas de corazón de res marinadas en ají panca y vinagre a la parrilla.',
                'porciones' => 4,
                'tiempo_preparacion' => 120,
                'tips' => 'El secreto es el macerado.',
                'categoria' => 'Piqueos',
                'ingredientes' => [
                    ['nombre' => 'Corazón de res', 'cantidad' => 1, 'unidad' => 'kg', 'notas' => 'Limpio'],
                    ['nombre' => 'Ají panca molido', 'cantidad' => 4, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Vinagre tinto', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Ajo molido', 'cantidad' => 1, 'unidad' => 'cucharada', 'notas' => ''],
                    ['nombre' => 'Comino', 'cantidad' => 1, 'unidad' => 'cucharadita', 'notas' => ''],
                    ['nombre' => 'Orégano seco', 'cantidad' => 1, 'unidad' => 'cucharadita', 'notas' => ''],
                ],
                'pasos' => [
                    'Mezclar en un bol el ají panca, vinagre, ajo, comino y orégano.',
                    'Añadir los trozos de corazón, mezclar y dejar macerar.',
                    'Ensartar de 3 a 4 trozos en cada palito de madera.',
                    'Cocinar en la parrilla caliente, untando la mezcla con una brocha.',
                    'Servir con papas doradas y ají carretillero.',
                ],
            ],
            [
                'nombre' => 'Seco de Res con Frijoles',
                'descripcion' => 'Guiso de res al culantro cocido a fuego lento con frijoles canarios.',
                'porciones' => 5,
                'tiempo_preparacion' => 90,
                'tips' => 'Usa asado de tira para mayor sabor.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Carne de res', 'cantidad' => 800, 'unidad' => 'gramos', 'notas' => 'Asado de tira'],
                    ['nombre' => 'Culantro licuado', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Chicha de jora', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => 'O cerveza'],
                    ['nombre' => 'Cebolla picada', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => ''],
                    ['nombre' => 'Zanahoria', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'En rodajas'],
                    ['nombre' => 'Frijoles canarios', 'cantidad' => 500, 'unidad' => 'gramos', 'notas' => 'Cocidos'],
                ],
                'pasos' => [
                    'Sellar la carne en una olla y retirar.',
                    'Hacer un aderezo de cebolla, ajo y ají amarillo molido.',
                    'Agregar culantro licuado y cocinar bien.',
                    'Volver la carne, añadir chicha y agua. Cocinar a fuego lento 1 hora.',
                    'Agregar zanahorias al final. Servir el guiso con frijoles y arroz.',
                ],
            ],
            [
                'nombre' => 'Tallarines Verdes',
                'descripcion' => 'Pasta bañada en salsa de espinaca y albahaca, estilo pesto peruano.',
                'porciones' => 4,
                'tiempo_preparacion' => 30,
                'tips' => 'Acompaña con un churrasco apanado o huevo frito.',
                'categoria' => 'Pastas',
                'ingredientes' => [
                    ['nombre' => 'Fideos tallarín grueso', 'cantidad' => 500, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Hojas de espinaca', 'cantidad' => 300, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Hojas de albahaca', 'cantidad' => 100, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Queso fresco', 'cantidad' => 150, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Leche evaporada', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Pecanas', 'cantidad' => 50, 'unidad' => 'gramos', 'notas' => ''],
                ],
                'pasos' => [
                    'Sancochar la pasta según instrucciones y reservar.',
                    'Pasar hojas de espinaca y albahaca por agua hirviendo un minuto.',
                    'Licuar espinaca, albahaca, queso, leche y pecanas.',
                    'Calentar ligeramente la salsa y mezclar con la pasta.',
                    'Servir inmediatamente.',
                ],
            ],
            [
                'nombre' => 'Rocoto Relleno',
                'descripcion' => 'Rocotos al horno rellenos de un picadillo de carne y queso.',
                'porciones' => 4,
                'tiempo_preparacion' => 60,
                'tips' => 'Hierve los rocotos para quitar el picor.',
                'categoria' => 'Entradas Calientes',
                'ingredientes' => [
                    ['nombre' => 'Rocotos', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => 'Grandes'],
                    ['nombre' => 'Carne molida de res', 'cantidad' => 400, 'unidad' => 'gramos', 'notas' => ''],
                    ['nombre' => 'Cebolla', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Picada fina'],
                    ['nombre' => 'Maní tostado', 'cantidad' => 3, 'unidad' => 'cucharadas', 'notas' => 'Triturado'],
                    ['nombre' => 'Huevo duro', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'Picados'],
                    ['nombre' => 'Queso paria o fresco', 'cantidad' => 150, 'unidad' => 'gramos', 'notas' => 'En láminas'],
                ],
                'pasos' => [
                    'Cortar la parte superior de los rocotos, quitar semillas. Hervirlos 2 veces.',
                    'Hacer el relleno: sofreír cebolla, ajo, ají panca. Añadir la carne y cocinar.',
                    'Añadir maní, huevo duro picado y salpimentar.',
                    'Rellenar cada rocoto con esta mezcla.',
                    'Colocar queso encima y cubrir con su tapita. Hornear 20 mins.',
                ],
            ],
            [
                'nombre' => 'Chupe de Camarones',
                'descripcion' => 'Sopa espesa y sustanciosa típica de Arequipa.',
                'porciones' => 4,
                'tiempo_preparacion' => 50,
                'tips' => 'Haz un caldo concentrado con las cabezas de camarones licuadas.',
                'categoria' => 'Sopas',
                'ingredientes' => [
                    ['nombre' => 'Camarones', 'cantidad' => 1, 'unidad' => 'kg', 'notas' => 'Limpios'],
                    ['nombre' => 'Ají panca y ají amarillo', 'cantidad' => 2, 'unidad' => 'cucharadas', 'notas' => 'Pastas'],
                    ['nombre' => 'Arroz', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Papa amarilla', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => 'Peladas'],
                    ['nombre' => 'Queso fresco', 'cantidad' => 200, 'unidad' => 'gramos', 'notas' => 'En cubos'],
                    ['nombre' => 'Leche evaporada', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Huevos', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => ''],
                ],
                'pasos' => [
                    'Preparar aderezo con cebolla, ajos, ají panca y ají amarillo.',
                    'Añadir el caldo de camarones y dejar hervir.',
                    'Añadir arroz y papas. Cocinar hasta que estén tiernos.',
                    'Agregar los camarones y cocinar por 3 minutos.',
                    'Añadir los huevos, el queso y finalmente la leche. Servir.',
                ],
            ],
            [
                'nombre' => 'Tacu Tacu',
                'descripcion' => 'Aprovechamiento de menestras mezcladas con arroz y sofritas.',
                'porciones' => 2,
                'tiempo_preparacion' => 20,
                'tips' => 'Debe quedar crocante por fuera.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Frijoles canarios', 'cantidad' => 2, 'unidad' => 'tazas', 'notas' => 'Cocidos'],
                    ['nombre' => 'Arroz blanco', 'cantidad' => 2, 'unidad' => 'tazas', 'notas' => 'Cocido'],
                    ['nombre' => 'Pasta de ají amarillo', 'cantidad' => 2, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Cebolla roja', 'cantidad' => 1, 'unidad' => 'unidad', 'notas' => 'Picada fina'],
                    ['nombre' => 'Aceite', 'cantidad' => 3, 'unidad' => 'cucharadas', 'notas' => ''],
                ],
                'pasos' => [
                    'Hacer un aderezo en una sartén con cebolla, ajo y ají amarillo.',
                    'Triturar un poco los frijoles y añadirlos a la sartén.',
                    'Agregar el arroz y mezclar todo hasta obtener una masa homogénea.',
                    'Dorar la mezcla dándole forma ovalada, dorando ambos lados.',
                    'Servir con huevo frito, plátano frito y salsa criolla.',
                ],
            ],
            [
                'nombre' => 'Carapulcra',
                'descripcion' => 'Guiso milenario andino hecho de papa seca, cerdo y ají panca.',
                'porciones' => 6,
                'tiempo_preparacion' => 120,
                'tips' => 'El toque de chocolate al final le da brillo.',
                'categoria' => 'Platos de Fondo',
                'ingredientes' => [
                    ['nombre' => 'Papa seca', 'cantidad' => 500, 'unidad' => 'gramos', 'notas' => 'Remojada'],
                    ['nombre' => 'Carne de cerdo', 'cantidad' => 600, 'unidad' => 'gramos', 'notas' => 'Panceta'],
                    ['nombre' => 'Ají panca molido', 'cantidad' => 5, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Maní tostado', 'cantidad' => 100, 'unidad' => 'gramos', 'notas' => 'Triturado'],
                    ['nombre' => 'Chocolate bitter', 'cantidad' => 1, 'unidad' => 'trozo', 'notas' => 'Pequeño'],
                    ['nombre' => 'Canela y clavo', 'cantidad' => 1, 'unidad' => 'pizca', 'notas' => ''],
                ],
                'pasos' => [
                    'Tostar la papa seca y remojar en agua tibia por 2 horas.',
                    'Dorar los trozos de cerdo en una olla y retirar.',
                    'En la misma grasa, hacer aderezo con cebolla, ajo y ají panca.',
                    'Añadir la papa seca, el cerdo, especias y cubrir con caldo. Hervir 45 mins.',
                    'Agregar el maní y el chocolatito. Servir.',
                ],
            ],
            [
                'nombre' => 'Aguadito de Pollo',
                'descripcion' => 'Una sopa reponedora y espesa a base de arroz y culantro.',
                'porciones' => 4,
                'tiempo_preparacion' => 45,
                'tips' => 'Añade un toque de chicha de jora al aderezo.',
                'categoria' => 'Sopas',
                'ingredientes' => [
                    ['nombre' => 'Presas de pollo', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => 'Menudencias'],
                    ['nombre' => 'Arroz', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => ''],
                    ['nombre' => 'Culantro licuado', 'cantidad' => 4, 'unidad' => 'cucharadas', 'notas' => ''],
                    ['nombre' => 'Verduras picadas', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => 'Zanahoria, alverja'],
                    ['nombre' => 'Papa amarilla', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => 'Partidas'],
                ],
                'pasos' => [
                    'En una olla dorar las presas de pollo y retirarlas.',
                    'En la misma olla dorar cebolla y ajo. Añadir el culantro y cocinar.',
                    'Agregar caldo, regresar el pollo y dejar hervir 15 minutos.',
                    'Añadir el arroz y las verduras.',
                    'Al final añadir la papa amarilla para espesar.',
                ],
            ],
            [
                'nombre' => 'Suspiro a la Limeña',
                'descripcion' => 'Postre limeño de crema dulce coronada con merengue al oporto.',
                'porciones' => 6,
                'tiempo_preparacion' => 60,
                'tips' => 'Cocina el manjar a fuego bajo sin dejar de mover.',
                'categoria' => 'Postres',
                'ingredientes' => [
                    ['nombre' => 'Leche evaporada', 'cantidad' => 1, 'unidad' => 'lata', 'notas' => ''],
                    ['nombre' => 'Leche condensada', 'cantidad' => 1, 'unidad' => 'lata', 'notas' => ''],
                    ['nombre' => 'Yemas de huevo', 'cantidad' => 4, 'unidad' => 'unidades', 'notas' => ''],
                    ['nombre' => 'Claras de huevo', 'cantidad' => 2, 'unidad' => 'unidades', 'notas' => ''],
                    ['nombre' => 'Oporto', 'cantidad' => 0.5, 'unidad' => 'taza', 'notas' => 'Vino dulce'],
                    ['nombre' => 'Azúcar blanca', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => ''],
                ],
                'pasos' => [
                    'Mezclar las leches. Cocinar a fuego lento moviendo hasta ver el fondo.',
                    'Retirar del fuego y añadir las yemas batiendo rápido. Servir en copas.',
                    'Preparar un almíbar con el azúcar y oporto a punto hilo.',
                    'Batir las claras a nieve e ir echando el almíbar caliente batiendo.',
                    'Coronar las copas con el merengue y espolvorear canela.',
                ],
            ],
        ];

        DB::transaction(function () use ($recetas, $admin) {
            foreach ($recetas as $index => $datos) {
                $categoria = Categoria::firstOrCreate(['nombre' => $datos['categoria']]);

                $receta = Receta::create([
                    'nombre' => $datos['nombre'],
                    'descripcion' => $datos['descripcion'],
                    'porciones' => $datos['porciones'],
                    'tiempo_preparacion' => $datos['tiempo_preparacion'],
                    'tips' => $datos['tips'],
                    'imagen' => 'temporal.png', // Temporary value to pass validation
                    'creado_por' => $admin->id,
                    'publicada_en' => now(),
                ]);

                $imagenRuta = 'recetas/'.$receta->id.'/portada.png';
                Storage::disk('local')->put(
                    $imagenRuta,
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=')
                );
                $receta->update(['imagen' => $imagenRuta]);

                $receta->categorias()->attach($categoria->id);

                foreach ($datos['ingredientes'] as $orden => $ing) {
                    $ingredienteModel = Ingrediente::firstOrCreate(['nombre' => $ing['nombre']]);
                    $receta->ingredientes()->attach($ingredienteModel->id, [
                        'cantidad' => $ing['cantidad'],
                        'unidad' => $ing['unidad'],
                        'notas' => $ing['notas'],
                        'orden' => $orden + 1,
                    ]);
                }

                foreach ($datos['pasos'] as $orden => $instruccion) {
                    $receta->pasos()->create([
                        'orden' => $orden + 1,
                        'instruccion' => $instruccion,
                    ]);
                }
            }
        });

        $this->command->info('¡Se han insertado 15 recetas peruanas reales exitosamente!');
    }
}
