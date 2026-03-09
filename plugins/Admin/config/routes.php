<?php

use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::plugin(
    'Admin',
    ['path' => '/'],
    function ($routes) {
        $action = isset($_GET['action'])? $_GET['action'] : (isset($_POST['action'])? $_POST['action'] : 'index');
        
        $routes->get('/modules.js', ['controller' => 'Modulos', 'action' => 'modules']);

        $routes->post('/_/*', ['controller' => 'Usuarios', 'action' => '_' ]);

        $routes->post('/menu/*', ['controller' => 'Menu', 'action' => $action ]);
        $routes->post('/roles/*', ['controller' => 'Roles', 'action' => $action ]);
        $routes->post('/modulos/*', ['controller' => 'Modulos', 'action' => $action ]);
        $routes->post('/usuarios/*', ['controller' => 'Usuarios', 'action' => $action]);
        $routes->post('/permisos/*', ['controller' => 'Permisos', 'action' => $action ]);
        $routes->post('/u/*', ['controller' => 'Usuarios', 'action' => $action ]);
        $routes->post('/usuarios/*', ['controller' => 'Usuarios', 'action' => $action ]);
        $routes->post('/recursos/*', ['controller' => 'Recursos', 'action' => $action ]);
        $routes->post('/quality/*', ['controller' => 'QualityAssurance', 'action' => $action ]);
        $routes->post('/paymentsmd/*', ['controller' => 'Paymentsmd', 'action' => $action ]);
        $routes->post('/patients/*', ['controller' => 'Patients', 'action' => $action ]);
        
        // $routes->get('/cjs/:files/*', ['controller' => 'Assets', 'action' => 'cjs']);
        // $routes->get('/ccss/:files/*', ['controller' => 'Assets', 'action' => 'ccss']);
    }
);