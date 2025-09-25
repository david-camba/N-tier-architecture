<?php
return [
    'error_log_path' => '/logs/app_error.log',
    'log_arg_length_limit' => 1024, // 0 para ilimitado. 1024 = 1KB
    // Types of components that can be modify, extend or overwrite on superior layers, each one associate with their directory
    'component_types' => [
        'controller' => 'controllers',
        'model'      => 'models',
        'view'       => 'views',
        'service'    => 'services',
        'factory'    => 'factories',
        'helper'     => 'helpers', 
        'translation' => 'services/translations', //added to support layered translations for the service "Translator"       
        //,'fastfix'     => 'fastfixes' 
        // we can include this to add N new types of components with a specific purposes
    ],

    // Layers map and priority order, to find classes and instance them
    'layers' => [
        'audi' => [
            'directory' => '3audi',          // Directory
            'suffix'    => '3Audi',
            'layer'     =>  3,          // Class suffix, for proper heritage 
        ],
        'vwgroup' => [
            'directory' => '2vwgroup',
            'suffix'    => '2GrupoVW',
            'layer'     =>  2,
        ],
        'base' => [
            'directory' => '1base',
            'suffix'    => 'Base',
            'layer'     =>  1,
        ]
    ],
    /* NOTE: we could simplify to numbers and add N layers and levels of functionalities and show them regarding the level of the user (for premium packages, for example)    
        
    'layers' => [
        'level4' => [
            'directory' => 'level4',      
            'suffix'    => '4'        //to name like: class MapController_4 extends MapController_3   
        ],
    */

    // Horizontal Layers map, find the specific controller or controller method for the user role
    'user_roles' => [
    3 => 'Admin',
    2 => 'Manager',
    1 => 'Seller',
    ],

    // Helper methods used for dynamic parent calls, skipped when resolving the real calling method
    'parent_call_helpers' => [
        'callParent',
        'parentResponse',
        //'parentService',
    ],

    // Relate models with their database
    'model_connections' => [
        'User' => 'master',
        'UserSession' => 'master',
        'Request' => 'master',

        'Client' => 'dealer',
        'ConfSession' => 'dealer',

        'CarModel' => 'productAudi',
        'Color' => 'productAudi',
        'Extra' => 'productAudi',
    ],

    // General context for our specific app case
    'general' => [
        'brandName' => 'Audi'
    ],
];