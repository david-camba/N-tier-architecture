<?php
// English Translations File

return [
    // --- Login Page ---
    'login_page_title' => 'nTier & LifeTree – Demo',
    'login_header_brand' => 'Welcome to %s', // %s will be replaced by the brand name
    'login_form_username_placeholder' => 'Username',
    'login_form_password_placeholder' => 'Password',
    'login_form_submit_button' => 'Login',
    'login_form_guest_access' => 'Continue as guest',
    'login_translation_message' => 'Choose language:',    
    'login_userlevel_warning' => 'IMPORTANT: You cannot have a User Level (Seller, Manager, etc.) unless you are logged in. And please, definitely don’t use the debug panel for that 😉',
    'login_userlevel_joke' => 'Okay, you actually used it... looks like the layer architecture can handle even this 😄',  
    
    // --- Login API Messages ---
    'login_api_error_credentials' => 'Incorrect username or password.',
    'login_api_error_missing_fields' => 'Username and password are required.',
    'login_api_error_account_locked' => 'Too many failed attempts. Your account has been temporarily locked.',
    'login_api_success_redirecting' => 'Login successful! Redirecting...',

    // --- Dashboard & Layout ---
    'menu_dashboard' => 'Dashboard',
    'menu_configurator' => 'Order Configurator - powered by LifeTree.js',
    'menu_clients_legacy' => 'Clients (Legacy Integration)',
    'menu_user_management' => 'User Management',
    'menu_report' => 'Report (only for managers)',
    'user_greeting' => 'Hello',
    'menu_logout' => 'Logout',
    'dashboard_title' => 'Main Dashboard',
    'dashboard_welcome_message' => 'Welcome back! From here you can manage the application.',
    'dashboard_welcome_message' => 'Welcome back! Wishing you lots of sales today.',
    'dashboard_welcome_message_manager' => 'Welcome back! You’re the best manager ever.',

    'views_layer_message' => '"The layer system allows creating child views in superior layers not only of the main view itself, but also of the templates it imports.", said the imported template in layer 3.',
    'dashboard_vw_message' => 'VOLKSWAGEN cares about clean energy.<br><i style="color: lightgreen;">Reminder: our focus should always be on reducing emissions.</i> Reminder sponsored by the main view in layer 2.',
    'dashboard_manager_message' => 'You are the manager of the year!',

    'report_team_title' => 'These people work for you, they are awesome.',
    
    'name_tag' => 'Name',
    'user_tag' => 'User',
    'email_tag' => 'Email',
    'role_tag' => 'Role',
    'adress_tag' => 'Address',
    'age_tag' => 'Age',
    'salary_tag' => 'Estimated Salary (Exclusive Audi Data)',
    'financing_tag' => 'Access to Financing',
    'registrationdate_tag' => 'Registration Date',
    'totalprice_tag' => 'Total Price',
    'proposaldate_tag' => 'Proposal Date',
    'backmenu_tag' => 'Return to Menu',
    'model_tag' => 'Model',
    'price_tag' => 'Price',
    'emissions_tag' => 'Emissions',  
    'from_tag' => 'From',
    

    'report_models_title' => 'Emissions Report',

    'clients_title' => 'Legacy Code - Client Search',
    'clients_intro_message' => 'Type "javier" or other letters to search for a client',
    'clients_intro_message_assigning' => 'Type "javier" or other letters to search for a client and assign your proposal',
    'clients_only_managers' => 'Access restricted to managers.',
    'clients_details' => 'Client Details',
    'clients_proposals_title' => 'Related Proposals',
    'clients_search' => 'Search client by name...',

    'role_M' => 'Manager',
    'role_S' => 'Seller',

    // --- Configurator Page ---
    'configurator_title' => 'LifeTree, the Dynamic Tree',
    'configurator_templates_label' => 'Load template:',
    'configurator_templates_default_option' => 'Select a template',
    'configurator_no_templates_message' => 'You have no saved templates.',
    'configurator_step1_title' => 'Choose a model',
    'configurator_step2_title' => 'Choose a color',
    'configurator_step3_title' => 'Extras',
    'configurator_step4_title' => 'Assign Client',
    'configurator_loading_message' => 'Loading configurator...',
    'configurator_price_label' => 'Estimated Total Price',
    'configurator_save_template_button' => 'Save as Template',
    'configurator_assign_client_button' => 'Assign to Client',

    'configurator_loading_models' => 'Loading models...',
    'configurator_next_button' => 'Next',
    'configurator_loading_colors' => 'Loading colors...',
    'configurator_back_button' => 'Back',
    'configurator_extras_picker_title'     => 'Choose as many extras as you want',
    'configurator_model_card_price_prefix' => 'From ',
    'configurator_model_picker_title'      => 'Choose your favorite model',
    'configurator_summary_title'           => 'Purchase Summary',
    'configurator_summary_final_price'     => 'Final Price',
    'configurator_summary_no_extras'       => 'No extras selected',

    // --- Color Names ---
    'color.black_mythos' => 'Mythos Black',
    'color.silver_floret' => 'Floret Silver',
    'color.white_glacier' => 'Glacier White',
    'color.black_orca' => 'Orca Black',
    'color.red_matador' => 'Passion Red',
    'color.silver_cuvee' => 'Cuvée Silver',
    'color.white_ibis' => 'Ibis White',

    // --- Extras Names ---
    'extra.rims_21' => '21" alloy wheels',
    'extra.rims_21_desc' => '5-V-spoke design, contrast grey, partly polished.',
    'extra.rims_19' => '19" alloy wheels',
    'extra.rims_19_desc' => 'Multi-spoke design, bronze finish.',
    'extra.pkg_city' => 'City assist package',
    'extra.pkg_city_desc' => 'Includes Audi pre sense 360°, cross traffic assist and exit warning.',
    'extra.pkg_tour' => 'Tour assist package',
    'extra.pkg_tour_desc' => 'Adaptive cruise control with traffic jam assist.',
    'extra.seats_sport' => 'S sport seats',
    'extra.seats_sport_desc' => 'Valcona leather upholstery with diamond quilting and S embossing.',
    'extra.seats_comfort' => 'Comfort seats with memory',
    'extra.seats_comfort_desc' => 'Full electric adjustment and memory function for the driver\'s seat.',
    'extra.sunroof' => 'Panoramic sliding sunroof',
    'extra.sunroof_desc' => 'Two-piece glass with electric operation for ventilation and opening.',
    'extra.headlights_matrix' => 'HD Matrix LED headlights',
    'extra.headlights_matrix_desc' => 'With dynamic indicators and long-range Audi laser light.',
    'extra.sound_bo' => 'Bang & Olufsen 3D sound system',
    'extra.sound_bo_desc' => 'Premium sound with 19 speakers, subwoofer, and 755 watts of power.',
    'extra.headup' => 'Head-up display',
    'extra.headup_desc' => 'Projects relevant driving information directly onto the windscreen.',
    'extra.suspension_air' => 'Adaptive air suspension',
    'extra.suspension_air_desc' => 'Electronic adjustment of suspension height and damping.',
    'extra.steering_flat' => 'Flat-bottomed sport steering wheel',
    'extra.steering_flat_desc' => '3-spoke design in perforated leather, with shift paddles.',

    'not_found_message' => 'Upps, page not found... URL requested: %s',
    'go_back_main' => 'Go back to the main page',

    'clients_error_no_session' => 'Error: The configuration does not exist.',
    'clients_error_not_owner' => 'Error: This configuration does not belong to you. You cannot assign it.',
    'clients_warning_already_assigned' => 'Warning: This configuration has already been assigned to a client. It cannot be reassigned, create a new configuration.',
    'clients_success_assigned' => 'Proposal successfully assigned to the client!',
    'clients_error_assign_failed' => 'Error assigning the proposal. Please try again.',
    'clients_error_invalid_configuration' => 'Error: The configuration is invalid. Make sure the model and color are assigned.',

    'banner_github_invitation' => 'github.com/nTier-backend-framework',
    'banner_github_invitation_tree' => 'github.com/LifeTree-frontend-framework',
    'banner_little_prince' => 'What is essential is invisible to the eye. - The Little Prince',
    'banner_lifetree_quote' => 'Trees are poems the earth writes upon the sky. - Kahlil Gibran',

    'login_explanation' => "This demo runs on a fully proprietary and complete development ecosystem:

    <br/><br/>• <b>nTier</b>: MVCS Backend Framework featuring a hybrid layer architecture (vertical/horizontal), a dependency injection engine, and native support for integration testing.

    <br/><br/>• <b>LifeTree</b>: Declarative Frontend Framework that defines the UI as a Dynamic Tree following its own composition philosophy — the 'Director, Stage, Actor' model. Performs surgical DOM updates without transpilation.",

];