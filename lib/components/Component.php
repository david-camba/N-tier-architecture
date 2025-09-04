<?php
class Component
{
    protected array $parentCallHelpers = [
        'callParent',
        'parentResponse',
        'parentReturn',
        // 'parentService',
    ];

    protected TranslatorService $translator;
    
    /**
     * It is a parent :: calling method () dynamic that facilitates syntax
     * Execute the same method of the object in the father class and return your answer.
     * Automatically detects the name of the method that called it and adjusts the arguments according to the father.
     * @param object $callerObject Receive the object that calls it to be able to execute the method maintaining the State
     * @return mixed The response of the Padre del Controller method, Service, Model ...
     */
    protected function callParent(object $callerObject) : mixed
    {
        // 1. detect the method and class that called us
        $backtrace = debug_backtrace(0, 5); // We get the last 5 calls to ensure us to find the real caller

        // 2. We ignore the intermediate methods (if they exist) to obtain the real call method
        $callHelpers = $this->parentCallHelpers;
        // If the amount of assistant methods could be extended, it could be put in a config

        $callerInfo = null;
        // We start in index 1, because 0 is always the current method.
        for ($i = 1; $i < count($backtrace); $i++) {
            $frame = $backtrace[$i];            
            // If the name of the current function is not on our list of assistants, then we have found the "real caller."
            if (!in_array($frame['function'], $callHelpers)) {
                $callerInfo = $frame;
                break; 
            }
        }

        if (!$callerInfo) {
            throw new LogicException("No se pudo determinar el método llamante real.");
        }

        $callerClassName = $callerInfo['class'] ?? null;
        $callerMethodName = $callerInfo['function'] ?? null;
        $callerArgs = $callerInfo['args'] ?? [];

        if (!$callerMethodName) {
            throw new LogicException("No se pudo determinar el método o clase que llama a getParentView.");
        }        

        // 2. get the real father and prepare the method
        $parentClass = get_parent_class($callerClassName);
        if (!method_exists($parentClass, $callerMethodName)) {
            throw new LogicException("El método {$callerMethodName} no existe en la clase padre {$parentClass}.");
        }        

        // 3. Execute the father's method in the context of the current $this
        $method = new ReflectionMethod($parentClass, $callerMethodName); // We create a reflection of the method

        $numParams = $method->getNumberOfParameters(); 
        $callerArgs = array_slice($callerArgs, 0, $numParams);

        $method->setAccessible(true); //Remove the protect for the reflection method can be launched

        $response = $method->invokeArgs($callerObject, $callerArgs); //We invoke about $this (The object of the upper layer you have called, allows us to maintain the State, and we pass the arguments)
        return $response;
    }

        /**
     * Shortcut to obtain a translated text chain.
     *
     * @param string $key
     * @param array $replacements
     * @return void
     */

    protected function translate(string $key, array $replacements = []) : string
    {
        return $this->translator->get($key, $replacements);
    }

    protected function getConfig($key, $default = null)
    {
        return App::getInstance()->getConfig($key, $default);
    }
    
    protected function getContext($key, $default = null)
    {
        return App::getInstance()->getContext($key, $default);
    }

    /**
     * Execute the same method in the father class and return your answer.
     * Automatically detects the name of the method that called it and adjusts the arguments according to the father.
     * 
     * @return mixed The response of the father method
     */
    protected function parentReturn() : mixed
    {
        $parentReturn = $this->callParent($this);
        return $parentReturn;
    }
}