<?php
interface ConfiguratorService{}

class ConfiguratorService_Base extends Service implements ConfiguratorService
    {

    protected TranslatorService $translator;
    protected ConfSession $confSessionModel;
    protected CarModel $carModel;
    protected Color $colorModel;
    protected Extra $extraModel;

    public function __construct(TranslatorService $translator, ConfSession $confSessionModel, CarModel $carModel, Color $colorModel, Extra $extraModel)
    {
        $this->translator = $translator;
        $this->confSessionModel = $confSessionModel;
        $this->carModel = $carModel;
        $this->colorModel = $colorModel;
        $this->extraModel = $extraModel;
    }


    public function saveExtras(ConfSession_Base $confSession, $extraIds)
    {
        // Los extras vienen como un array de IDs.
        $carModelId = $confSession->id_model;  
        $compatibleExtras = $this->carModel->find($carModelId)->extras;
        $compatibleExtrasIds = $compatibleExtras->pluck('id_extra');
        $invalidIds = array_diff($extraIds, $compatibleExtrasIds);

        if (!empty($invalidIds)) {
            // Opcional: puedes hacer el mensaje de error más específico.
            $invalidIdList = implode(', ', $invalidIds);
            throw new Exception("Se han enviado extras no válidos o no compatibles. Los siguientes IDs de extra no son válidos para este modelo: {$invalidIdList}.", 400);
        }

        // 1. Guardamos los extras como un string separado por comas.
        $confSession->extras = implode(',', $extraIds);
        $confSession->save();
    }

    public function getSummaryData(ConfSession_Base $confSession)
    {
        // Modelo
        $carModel = $this->carModel->find($confSession->id_model);
        
        // Color
        $color = $this->colorModel->find($confSession->id_color);        
        
        // Extras (necesitamos los objetos completos, no solo los IDs)
        $extraIds = explode(',', $confSession->extras);
        $extras = [];
        if (!empty($extraIds)) {
            $allExtras = $this->extraModel->findAll('id_extra', $extraIds); // findAll puede buscar por un array de IDs
            $extras = $allExtras->toArray();
        }
            
        // 3. Devolvemos una respuesta de éxito con todos los datos del resumen.
        return [
            'model' => $carModel ? $carModel->toArray() : null,
            'color' => $color ? $this->_translateAndFormatColor($color) : null,
            'extras' => $this->_translateAndFormatExtras($extras),
        ];
    }

    /**
     * Calcula el precio total para una sesión de configuración dada.
     *
     * @param ConfSession_Base $confSession El objeto de la sesión de configuración.
     * @return float El precio total calculado.
     */
    public function calculateTotal(ConfSession_Base $confSession)
    {
        $total = 0.0;

        // 1. Añadir el precio base del modelo.
        if ($confSession->id_model) {
            $total += $this->getCarModelPrice($confSession->id_model);
        }

        // 2. Añadir el sobrecoste del color.
        if ($confSession->id_color) {
            $color = $this->colorModel->find($confSession->id_color);
            if ($color) {
                $total += (float)$color->price_increase;
            }
        }
        
        // 3. Añadir el precio de los extras.
        if ($confSession->extras) {
            $extraIds = explode(',', $confSession->extras);
            if (!empty($extraIds)) {
                $extrasCollection = $this->extraModel->findAll('id_extra', $extraIds);
                
                // Usamos reduce para sumar los precios de la colección.
                $totalExtras = $extrasCollection->reduce(function ($sum, $extra) {
                    return $sum + (float)$extra->price;
                }, 0);

                $total += $totalExtras;
            }
        }

        return $total;
    }

    /**
     * Calcula el precio total para una sesión de configuración dada.
     *
     * @return float El precio total calculado.
     */
    public function getCarModelPrice($carModelId)
    {
        $carModel = $this->carModel->find($carModelId);
        if ($carModel) {
            return (float)$carModel->price;
        }  
        else{
            throw new \Exception("Modelo no encontrado: " . $carModelId);
        }
    }

    // --- Helpers privados para mantener el código limpio ---
    protected function _translateAndFormatColor($color) {
        return ['name' => $this->translate($color->name), 'price' => (float) $color->price_increase];
    }

    protected function _translateAndFormatExtras($extras) {
        return array_map(function($extra) {
            return [
                'name' => $this->translate($extra['name']),
                'price' => (float) $extra['price']
            ];
        }, $extras);
    }

    /**
     * Calcula el precio total para una sesión de configuración dada.
     *
     * @param array $confSession El objeto de la sesión de configuración.
     * @return float El precio total calculado.
     */
    public function calculateTotalLegacy(array $confSession)
    {
        $total = 0.0;

        // 1. Añadir el precio base del modelo.
        if (!empty($confSession['id_model'])) {
            $total += $this->getCarModelPrice((int)$confSession['id_model']);
        }

        // 2. Añadir el sobrecoste del color.
        if (!empty($confSession['id_color'])) {
            $color = $this->colorModel->find((int)$confSession['id_color']);
            if ($color) {
                $total += (float)$color->price_increase;
            }
        }

        // 3. Añadir el precio de los extras.
        if (!empty($confSession['extras'])) {
            $extraIds = array_filter(explode(',', $confSession['extras']));
            if (!empty($extraIds)) {
                $extrasCollection = $this->extraModel->findAll('id_extra', $extraIds);
                
                // Usamos reduce para sumar los precios de la colección.
                $totalExtras = $extrasCollection->reduce(function ($sum, $extra) {
                    return $sum + (float)$extra->price;
                }, 0);

                $total += $totalExtras;
            }
        }

        return $total;
    }

    public function getSummaryDataLegacy(array $confSession)
    {
        // Modelo
        $carModel = null;
        if (!empty($confSession['id_model'])) {
            $carModel = $this->carModel->find((int)$confSession['id_model']);
        }

        // Color
        $color = null;
        if (!empty($confSession['id_color'])) {
            $color = $this->colorModel->find((int)$confSession['id_color']);
        }

        // Extras
        $extras = [];
        if (!empty($confSession['extras'])) {
            $extraIds = array_filter(explode(',', $confSession['extras']));
            if (!empty($extraIds)) {
                $allExtras = $this->extraModel->findAll('id_extra', $extraIds); // findAll soporta array
                $extras = $allExtras->toArray();
            }
        }

        // Respuesta con todos los datos del resumen
        return [
            'model' => $carModel ? $carModel->toArray() : null,
            'color' => $color ? $this->_translateAndFormatColor($color) : null,
            'extras' => $this->_translateAndFormatExtras($extras),
        ];
    }

    /**
     * Helper privado para obtener y formatear la lista de modelos.
     * Esta lógica ahora es reutilizable.
     *
     * @return array
     */
    public function _getAllCarModels()
    {
        // 1. Buscamos todos los modelos.
        $carModels = $this->carModel->all(); 

        // 2. Mapeamos y formateamos los datos.
        $data = $carModels->map(function ($carModel) {
            return [
                'id' => $carModel->id_model,
                'name' => $this->translate($carModel->name), // Aprovechamos para traducir
                'price' => (float) $carModel->price,
                'image' => $this->_getFirstColorImageForModel($carModel->id_model)
            ];
        });

        // Devolvemos la colección de datos formateados.
        return $data->toArray();
    }

    /**
     * Helper privado para obtener la imagen del primer color de un modelo.
     */
    protected function _getFirstColorImageForModel($carModelId)
    {
        $color = $this->colorModel->find($carModelId,"id_model");
        return $color ? $color->img : '/1base/img/default_model.jpg'; // Imagen de fallback
    }

    /**
     * Helper privado para obtener la imagen del primer color de un modelo.
     */
    public function carModelExists($carModelId)
    {
        $carModel = $this->carModel->find($carModelId);
        if (!$carModel) {
            return false;
        }
        return true;
    }

    public function getColorsForCarModel($carModelId)
    {
        
        $carModel = $this->carModel->find($carModelId);

        //testing hasMany() ORM relation
        return $carModel->colors;
    }

    public function getColor($colorId)
    {
        return $this->colorModel->find($colorId);
    }

    public function getExtrasForCarModel($carModelId)
    {
        //testing "belongsToMany" ORM relation
        return $this->carModel->find($carModelId)->extras;
    }

    public function prepareExtras($compatibleExtras)
    {
        return $compatibleExtras->map(function ($extra) {
            return [
                'id_extra' => $extra->id_extra,
                'name' => $this->translate($extra->name),
                'description' => $this->translate($extra->description),
                'price' => (float) $extra->price,
                'models' => $extra->models,
            ];
        });
    }

    /**
     * Formatea y traduce una colección de objetos Color para su uso en la API.
     *
     * @param Collection $colorsCollection La colección de objetos Color_Base.
     * @return array Un array de arrays con los datos de los colores listos para JSON.
     */
    public function prepareColors(Collection $colorsCollection)
    {
        // La lógica de transformación ahora vive aquí.
        $formatted = $colorsCollection->map(function ($color) {
            return [
                'id_color' => $color->id_color,
                'id_model' => $color->id_model,
                'name' => $this->translate($color->name),
                'img' => $color->img,
                'price_increase' => (float) $color->price_increase
            ];
        });
        
        // Devolvemos el array final.
        return $formatted->toArray(); //
    }

    


    

    
}