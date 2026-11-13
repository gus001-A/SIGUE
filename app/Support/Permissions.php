<?php

namespace App\Support;

/**
 * Catálogo de permisos de SIGUE y su asignación por defecto a cada perfil
 * (sección 4 de la especificación).
 */
class Permissions
{
    public const ALL = [
        // módulo => [slug => nombre]
        'General' => [
            'dashboard.ver' => 'Ver dashboard institucional',
            'mensajes.usar' => 'Usar mensajería',
        ],
        'Alumnos' => [
            'alumnos.ver' => 'Consultar alumnos y expedientes',
            'alumnos.editar' => 'Registrar y editar alumnos',
            'alumnos.salud' => 'Consultar expediente de salud',
        ],
        'Docentes' => [
            'docentes.ver' => 'Consultar docentes',
            'docentes.editar' => 'Registrar y editar docentes',
        ],
        'Padres o tutores' => [
            'tutores.ver' => 'Consultar padres y tutores',
            'tutores.editar' => 'Registrar tutores y vincular alumnos',
        ],
        'Académico' => [
            'academico.gestionar' => 'Gestionar grupos, asignaturas y horarios',
            'incidencias.ver' => 'Consultar incidencias institucionales',
        ],
        'Matriculación' => [
            'matricula.gestionar' => 'Inscripciones, reinscripciones y bajas',
            'matricula.autorizar' => 'Autorizar inscripciones y bajas',
            'documentos.validar' => 'Validar documentos de expedientes',
        ],
        'Administración' => [
            'finanzas.ver' => 'Consultar pagos y adeudos',
            'finanzas.gestionar' => 'Registrar pagos y adeudos',
            'becas.gestionar' => 'Administrar becas y descuentos',
        ],
        'Reportes' => [
            'reportes.ver' => 'Reportes académicos y administrativos',
            'reportes.financieros' => 'Reportes financieros',
        ],
        'Gestión y configuración' => [
            'usuarios.gestionar' => 'Administrar usuarios',
            'configuracion.gestionar' => 'Configuración institucional y catálogos',
        ],
        'Docencia' => [
            'docencia.usar' => 'Mis clases: asistencia, classroom, evaluación, incidencias y tutorías',
        ],
        'Portales' => [
            'portal.alumno' => 'Portal del alumno',
            'portal.tutor' => 'Portal de padres y tutores',
        ],
        'SIGUE' => [
            'instituciones.gestionar' => 'Administrar instituciones, licencias y suscripciones',
        ],
    ];

    public const ROLES = [
        'superadmin' => ['Administrador general SIGUE', 'Instituciones, licencias, suscripciones y soporte', 1, '*'],
        'director' => ['Director', 'Indicadores, autorizaciones, supervisión y reportes', 2, [
            'dashboard.ver', 'mensajes.usar', 'alumnos.ver', 'alumnos.salud', 'docentes.ver', 'tutores.ver',
            'academico.gestionar', 'incidencias.ver', 'matricula.gestionar', 'matricula.autorizar', 'documentos.validar',
            'finanzas.ver', 'becas.gestionar', 'reportes.ver', 'reportes.financieros',
        ]],
        'admin' => ['Administrador institucional', 'Usuarios, alumnos, grupos, pagos, documentos y catálogos', 3, [
            'dashboard.ver', 'mensajes.usar', 'alumnos.ver', 'alumnos.editar', 'alumnos.salud', 'docentes.ver', 'docentes.editar',
            'tutores.ver', 'tutores.editar', 'academico.gestionar', 'incidencias.ver', 'matricula.gestionar', 'matricula.autorizar',
            'documentos.validar', 'finanzas.ver', 'finanzas.gestionar', 'becas.gestionar', 'reportes.ver', 'reportes.financieros',
            'usuarios.gestionar', 'configuracion.gestionar',
        ]],
        'docente' => ['Docente', 'Grupos, asistencias, tareas, evaluaciones, incidencias y tutorías', 4, [
            'docencia.usar', 'mensajes.usar',
        ]],
        'tutor' => ['Padre o tutor', 'Consulta la información de sus hijos', 5, [
            'portal.tutor', 'mensajes.usar',
        ]],
        'alumno' => ['Alumno', 'Calificaciones, tareas, horarios, asistencias y documentos', 6, [
            'portal.alumno', 'mensajes.usar',
        ]],
    ];

    public static function flat(): array
    {
        return array_merge(...array_values(self::ALL));
    }

    public static function moduleOf(string $slug): string
    {
        foreach (self::ALL as $module => $perms) {
            if (isset($perms[$slug])) {
                return $module;
            }
        }
        return 'General';
    }
}
