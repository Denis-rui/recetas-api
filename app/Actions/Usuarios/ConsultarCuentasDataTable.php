<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Http\Request;

class ConsultarCuentasDataTable
{
    /**
     * Lista blanca de columnas permitidas para ordenamiento (previene inyecciones y errores SQL).
     *
     * @var array<int, string>
     */
    protected array $columnasPermitidas = [
        0 => 'name',
        1 => 'email',
        2 => 'rol',
        3 => 'activo',
        4 => 'id',
    ];

    /**
     * Procesa la consulta server-side para DataTables aplicando filtros seguros y debounce/abort.
     *
     * @return array<string, mixed>
     */
    public function ejecutar(Request $request, User $usuarioAutenticado): array
    {
        // 1. Parámetro draw para control de concurrencia y descarte de respuestas obsoletas
        $draw = (int) $request->input('draw', 1);

        // 2. Control de paginación con tope de seguridad (máximo 50 registros por petición)
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length < 5 || $length > 50) {
            $length = 10;
        }

        // 3. Conteo total sin filtros
        $recordsTotal = User::count();

        // 4. Construcción de consulta con filtros
        $query = User::query();

        // Filtro de búsqueda textual acotada (nombre o correo)
        $busqueda = trim((string) $request->input('search.value', ''));
        if ($busqueda !== '') {
            // Limitar longitud para evitar abusos
            $busquedaSanitizada = mb_substr($busqueda, 0, 100);
            $query->where(function ($sub) use ($busquedaSanitizada) {
                $sub->where('name', 'like', "%{$busquedaSanitizada}%")
                    ->orWhere('email', 'like', "%{$busquedaSanitizada}%");
            });
        }

        // Filtro adicional por Rol (validado con lista blanca)
        $filtroRol = $request->input('filtro_rol');
        if (in_array($filtroRol, ['administrador', 'usuario'], true)) {
            $query->where('rol', $filtroRol);
        }

        // Filtro adicional por Estado (validado con lista blanca)
        $filtroEstado = $request->input('filtro_estado');
        if ($filtroEstado === 'activo') {
            $query->where('activo', true);
        } elseif ($filtroEstado === 'deshabilitado') {
            $query->where('activo', false);
        }

        // 5. Conteo de registros filtrados
        $recordsFiltered = $query->count();

        // 6. Ordenamiento seguro mediante lista blanca
        $columnaOrdenIndice = (int) $request->input('order.0.column', 4);
        $columnaOrden = $this->columnasPermitidas[$columnaOrdenIndice] ?? 'id';
        $direccionOrden = strtolower((string) $request->input('order.0.dir', 'desc'));
        if (! in_array($direccionOrden, ['asc', 'desc'], true)) {
            $direccionOrden = 'desc';
        }

        $usuarios = $query->orderBy($columnaOrden, $direccionOrden)
            ->skip($start)
            ->take($length)
            ->get();

        // 7. Formateo y renderizado seguro de cada fila
        $data = [];
        $csrfToken = csrf_token();

        foreach ($usuarios as $u) {
            $esPropiaCuenta = ($u->id === $usuarioAutenticado->id);

            // Columna 0: Usuario (Avatar o iniciales + Nombre + ID)
            $avatarHtml = '';
            if ($u->foto_perfil) {
                $urlFoto = asset('storage/'.$u->foto_perfil);
                $avatarHtml = '<img src="'.e($urlFoto).'" alt="'.e($u->name).'" class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0">';
            } else {
                $avatarHtml = '<div class="w-10 h-10 rounded-full bg-amber-100 text-amber-800 font-bold text-xs flex items-center justify-center border border-amber-200 shrink-0">'.e($u->iniciales).'</div>';
            }

            $badgeTu = $esPropiaCuenta
                ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Tú</span>'
                : '';

            $columnaUsuario = '<div class="flex items-center space-x-3">'.
                $avatarHtml.
                '<div>'.
                    '<div class="font-semibold text-slate-900 text-sm flex items-center">'.e($u->name).$badgeTu.'</div>'.
                    '<div class="text-xs text-slate-400">ID: #'.$u->id.'</div>'.
                '</div>'.
            '</div>';

            // Columna 1: Correo electrónico
            $columnaEmail = '<span class="text-sm text-slate-600">'.e($u->email).'</span>';

            // Columna 2: Rol
            if ($u->rol === 'administrador') {
                $columnaRol = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">Administrador</span>';
            } else {
                $columnaRol = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">Usuario normal</span>';
            }

            // Columna 3: Estado
            if ($u->activo) {
                $columnaEstado = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">'.
                    '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>Activo</span>';
            } else {
                $columnaEstado = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">'.
                    '<span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>Deshabilitado</span>';
            }

            // Columna 4: Acciones protegidas (RN-06, RN-07)
            $urlEditar = route('usuarios.edit', $u);
            $accionesHtml = '<div class="flex items-center justify-end space-x-2">'.
                '<a href="'.e($urlEditar).'" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 transition" title="Editar cuenta">Editar</a>';

            if ($esPropiaCuenta) {
                $accionesHtml .= '<span class="text-xs text-slate-400 italic px-2">Cuenta protegida</span>';
            } else {
                // Botón Cambiar Rol (RF-07)
                $nuevoRol = ($u->rol === 'administrador') ? 'usuario' : 'administrador';
                $textoBotonRol = ($u->rol === 'administrador') ? 'Hacer Usuario normal' : 'Hacer Administrador';
                $urlCambiarRol = route('usuarios.cambiar-rol', $u);

                $accionesHtml .= '<form method="POST" action="'.e($urlCambiarRol).'" class="inline" onsubmit="return confirm(\'¿Confirma que desea cambiar el rol de '.e(addslashes($u->name)).'?\');">'.
                    '<input type="hidden" name="_token" value="'.$csrfToken.'">'.
                    '<input type="hidden" name="_method" value="PATCH">'.
                    '<input type="hidden" name="rol" value="'.$nuevoRol.'">'.
                    '<button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition cursor-pointer">'.$textoBotonRol.'</button>'.
                '</form>';

                // Botón Deshabilitar o Reactivar (RF-08, RF-09)
                if ($u->activo) {
                    $urlDeshabilitar = route('usuarios.deshabilitar', $u);
                    $accionesHtml .= '<form method="POST" action="'.e($urlDeshabilitar).'" class="inline" onsubmit="return confirm(\'¿Está seguro de deshabilitar la cuenta de '.e(addslashes($u->name)).'? Esta acción cerrará sus sesiones y bloqueará su acceso.\');">'.
                        '<input type="hidden" name="_token" value="'.$csrfToken.'">'.
                        '<input type="hidden" name="_method" value="PATCH">'.
                        '<button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer">Deshabilitar</button>'.
                    '</form>';
                } else {
                    $urlReactivar = route('usuarios.reactivar', $u);
                    $accionesHtml .= '<form method="POST" action="'.e($urlReactivar).'" class="inline" onsubmit="return confirm(\'¿Desea reactivar la cuenta de '.e(addslashes($u->name)).'?\');">'.
                        '<input type="hidden" name="_token" value="'.$csrfToken.'">'.
                        '<input type="hidden" name="_method" value="PATCH">'.
                        '<button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer">Reactivar</button>'.
                    '</form>';
                }
            }

            $accionesHtml .= '</div>';

            $data[] = [
                $columnaUsuario,
                $columnaEmail,
                $columnaRol,
                $columnaEstado,
                $accionesHtml,
            ];
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }
}
