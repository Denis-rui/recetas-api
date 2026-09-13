<?php

namespace App\Http\Requests\Revision;

use Illuminate\Foundation\Http\FormRequest;

class DecidirRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decidir', $this->route('solicitud')) ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo_rechazo' => ['nullable', 'string', 'max:16000'],
            'confirmar_correccion_menor' => $this->routeIs('revision-recetas.aprobar') && $this->route('solicitud')?->tipo === 'correccion'
                ? ['required', 'accepted'] : ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmar_correccion_menor.required' => 'Confirma que has revisado los cambios y que son una corrección menor.',
            'confirmar_correccion_menor.accepted' => 'Si transforma la preparación, rechaza la solicitud e indica que debe crearse una receta nueva.',
            'motivo_rechazo.string' => 'El motivo de rechazo debe ser texto.',
            'motivo_rechazo.max' => 'El motivo no puede superar los 16000 caracteres.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('revision-recetas.show', $this->route('solicitud'));
    }
}
