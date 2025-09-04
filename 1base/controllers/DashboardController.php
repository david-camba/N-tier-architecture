<?php
class DashboardController_Base extends Controller
{    
    
    public $userLevelFallback = true;

    //protected ContextService $context; //if we want pure DI, we could use ContextService & ConfigService. Now, we'll keep using the "Controller" method getContext that uses the App singleton

    protected TranslatorService $translator;
    protected MenuHelper $menuHelper;
    protected User $userModel;

    public function __construct(MenuHelper $menuHelper, TranslatorService $translator, User $userModel)
    {
        $this->translator = $translator;
        $this->menuHelper = $menuHelper;
        $this->userModel = $userModel;
    }

    public function showDashboard()
    {
        // 1. Obtenemos el usuario del contexto, que fue establecido durante la autenticación.
        $user = $this->getContext('user');
        
        // Si por alguna razón no hay usuario (aunque la seguridad debería haberlo prevenido),
        // podríamos manejarlo aquí, pero confiamos en el `handleSecurity`.

        // 2. Preparamos la vista 'dashboard' con sus datos.
        $view = $this->getView('dashboard', [
            'dashboard_welcome_message' => $this->translate('dashboard_welcome_message'),
            'views_layer_message'       => $this->translate('views_layer_message'),         
        ]);

        $view->add('scripts','/1base/js/dashboard_base.js');

        $this->menuHelper->prepareMenuData($view,'dashboard_title');
        
        // 3. Devolvemos la respuesta de la vista.
        return $this->view($view);
    }

    public function showDashboard_Manager(){
        $view = $this->showDashboard()->getContent();  
        
        $view->set('dashboard_welcome_message', $this->translate('dashboard_welcome_message_manager'));

        return $this->view($view);
    }
    
    public function showDashboard_Seller(){
        return $this->showDashboard();
    }

    public function showEmployeesReportAPI_Manager()
    {
        // 1. OBTENER EL USUARIO ACTUAL
        // El dispatcher ya ha verificado que el usuario es un Manager para llegar aquí.
        $manager = $this->getContext('user');
        
        // 2. OBTENER LOS DATOS DESDE EL MODELO
        // Pedimos a la fábrica un buscador de Usuarios.

        // Buscamos a todos los usuarios que pertenecen al mismo concesionario que el manager.
        $teamMembers = $this->userModel->findAll('id_dealer', $manager->id_dealer)
            ->filter(function ($user) use ($manager) {
                // filter() mantiene un elemento si el callback devuelve 'true'.
                // Queremos mantener a todos los usuarios CUYO ID SEA DIFERENTE
                // al del manager que pide el informe.
                return $user->id_user !== $manager->id_user;
            })
            ->map(function ($user) {
            return [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role_code' => $this->translate('role_'.$user->user_code),
            ];
        })->toArray(); // Suponiendo que Collection->toArray() convierte los objetos en arrays.

        // 4. DEVOLVER LA RESPUESTA JSON
        return $this->json(
            [
                'name_tag' => $this->translate('name_tag'),
                'user_tag' => $this->translate('user_tag'),
                'email_tag' => $this->translate('email_tag'),
                'role_tag' => $this->translate('role_tag'),
                'title' => $this->translate('report_team_title'),
                'team_members' => $teamMembers
            ]);
    }
}