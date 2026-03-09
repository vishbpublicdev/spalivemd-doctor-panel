<?php
    foreach($modules as $module){
        $file = APP . 'App' . DS . 'Modules' . DS . $module->file;
        if(file_exists($file)){
            echo "/************************ {$module->file} ************************/\n\n";
            include($file);
        }else{
            echo "/************************ Archivo: {$module->file} no existe. ************************/\n\n";
        }
    }
    $file = APP . 'App' . DS . 'Modules' . DS . 'quality_assurance_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }
    $file = APP . 'App' . DS . 'Modules' . DS . 'treatmentsmint_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }
    $file = APP . 'App' . DS . 'Modules' . DS . 'weightloss_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }
    $file = APP . 'App' . DS . 'Modules' . DS . 'treatments_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }

    $file = APP . 'App' . DS . 'Modules' . DS . 'payments_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }

    $file = APP . 'App' . DS . 'Modules' . DS . 'settings_module.js';
    if(file_exists($file)){
        echo "/************************ {$file} ************************/\n\n";
        include($file);
    }        
    else{
            echo "/************************ Archivo: {$file} no existe. ************************/\n\n";
    }
