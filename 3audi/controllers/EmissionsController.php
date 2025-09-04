<?php
class EmissionsController_3Audi extends Controller
{
    public $userLevelFallback = true;

    protected EmissionsService $emissionsService;

    public function __construct(TranslatorService $translator, EmissionsService $emissionsService)
    {
        $this->translator = $translator;
        $this->emissionsService = $emissionsService;
    }

    public function showReportEmissions()
    {
        // 1. OBTENER LOS DATOS DESDE EL MODELO
        // Usamos el modelo 'Model' (que apunta a la tabla de modelos de coches).
        //$reportData = $this->getService('Emissions')->getEmissionsData();

        $reportData = $this->emissionsService->getEmissionsData();

        // 3. DEVOLVER LA RESPUESTA JSON
        return $this->json(
            [
                'title'     => $this->translate('report_models_title'),
                'name_tag'  => $this->translate('model_tag'),
                'price_tag' => $this->translate('price_tag'),
                'emissions_tag' => $this->translate('emissions_tag'),
                'models'      => $reportData
            ]
        );
    }
}