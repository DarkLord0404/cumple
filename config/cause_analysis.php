<?php

return [
    'personal' => [
        'label' => 'Personal',
        'question' => '¿El evento se presenta en el personal?',
        'causes' => [
            'skill_technique' => 'Habilidad o técnica',
            'protocol_adherence' => 'Adherencia a protocolos, procesos o guías',
            'undefined_responsibility' => 'Responsabilidad y autoridad no definidas',
            'communication' => 'Comunicación',
            'attention_memory' => 'Fallas de atención o memoria',
            'ineffective_training' => 'Capacitación no eficaz',
            'lack_knowledge' => 'Falta de conocimiento',
            'other' => 'Otra',
        ],
    ],
    'resources' => [
        'label' => 'Recursos',
        'question' => '¿El evento se presenta por los recursos?',
        'causes' => [
            'supplies_medication' => 'Insuficiencia de insumos y/o medicamentos',
            'equipment_technology' => 'Insuficiencia de equipos o tecnología',
            'financial' => 'Financieros',
            'staff' => 'Insuficiencia del personal',
            'other' => 'Otra',
        ],
    ],
    'equipment' => [
        'label' => 'Equipos biomédicos y/o cómputo',
        'question' => '¿El evento se presenta por los equipos biomédicos y/o cómputo?',
        'causes' => [
            'unavailable' => 'No disponibilidad del equipo',
            'improper_use' => 'Uso inadecuado',
            'obsolete' => 'Obsolescencia del equipo',
            'maintenance' => 'Falta de mantenimiento',
            'other' => 'Otra',
        ],
    ],
    'procedures' => [
        'label' => 'Procedimientos',
        'question' => '¿El evento se presenta por los procedimientos?',
        'causes' => [
            'no_training' => 'No se realizó entrenamiento y/o inducción',
            'not_documented' => 'No está documentado el protocolo o guía',
            'outdated_document' => 'Desactualización del proceso, protocolo y guía',
            'other' => 'Otra',
        ],
    ],
    'environment' => [
        'label' => 'Medio o entorno',
        'question' => '¿El evento se presenta por el medio o el entorno?',
        'causes' => [
            'installed_capacity' => 'Capacidad instalada',
            'workload' => 'Cargas de trabajo',
            'patient_volume' => 'Volumen de pacientes',
            'work_climate' => 'Clima laboral',
            'patient_pressure' => 'Agresividad o presión del paciente',
            'infrastructure' => 'Infraestructura inadecuada',
            'other' => 'Otra',
        ],
    ],
    'measurement' => [
        'label' => 'Medición',
        'question' => '¿El problema se presenta por la medición?',
        'causes' => [
            'inadequate_supervision' => 'Inadecuada supervisión del personal',
            'inadequate_indicator_analysis' => 'Análisis inadecuado de indicadores',
            'lack_indicator_analysis' => 'Carencia de análisis de indicadores',
            'missing_indicators' => 'Falta de indicadores',
            'other' => 'Otra',
        ],
    ],
];
