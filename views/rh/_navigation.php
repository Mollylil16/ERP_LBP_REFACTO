<?php

$moduleNavigation = [
    ['group' => 'Pilotage', 'key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'DB', 'url' => 'rh/dashboard', 'available' => true],
    ['group' => 'Collaborateurs', 'key' => 'personnel', 'label' => 'Dossiers du personnel', 'icon' => 'PE', 'url' => 'rh/personnel', 'available' => true],
    ['group' => 'Collaborateurs', 'key' => 'organization', 'label' => 'Organisation & carrière', 'icon' => 'OR', 'url' => 'rh/cycle-vie?section=organization', 'available' => true],
    ['group' => 'Collaborateurs', 'key' => 'mutations', 'label' => 'Promotions & mutations', 'icon' => 'MU', 'url' => 'rh/mutations', 'available' => true],
    ['group' => 'Parcours & talents', 'key' => 'contracts', 'label' => 'Contrats & essais', 'icon' => 'CT', 'url' => 'rh/cycle-vie?section=contracts', 'available' => true],
    ['group' => 'Parcours & talents', 'key' => 'assignments', 'label' => 'Missions & affectations', 'icon' => 'MA', 'url' => 'rh/cycle-vie?section=assignments', 'available' => true],
    ['group' => 'Parcours & talents', 'key' => 'evaluations', 'label' => 'Performances', 'icon' => 'EV', 'url' => 'rh/cycle-vie?section=evaluations', 'available' => true],
    ['group' => 'Parcours & talents', 'key' => 'trainings', 'label' => 'Formation', 'icon' => 'FO', 'url' => 'rh/cycle-vie?section=trainings', 'available' => true],
    ['group' => 'Entrées & sorties', 'key' => 'recruitment', 'label' => 'Recrutement & onboarding', 'icon' => 'RC', 'url' => 'rh/cycle-vie?section=recruitment', 'available' => true],
    ['group' => 'Entrées & sorties', 'key' => 'sorties', 'label' => 'Départs & offboarding', 'icon' => 'SO', 'url' => 'rh/mouvements', 'available' => true],
    ['group' => 'Administration RH', 'key' => 'attendance', 'label' => 'Temps & présence', 'icon' => 'TP', 'url' => 'rh/pointage', 'available' => true],
    ['group' => 'Administration RH', 'key' => 'payroll', 'label' => 'Paie & variables', 'icon' => 'PA', 'url' => 'rh/paie', 'available' => true],
    ['group' => 'Administration RH', 'key' => 'discipline', 'label' => 'Discipline', 'icon' => 'DI', 'url' => 'rh/cycle-vie?section=discipline', 'available' => true],
    ['group' => 'Administration RH', 'key' => 'workflows', 'label' => 'Validations RH', 'icon' => 'WF', 'url' => 'rh/cycle-vie?section=workflows', 'available' => true],
    ['group' => 'Configuration', 'key' => 'settings', 'label' => 'Paramétrage', 'icon' => 'PR', 'url' => 'rh/parametrage', 'available' => true],
];
