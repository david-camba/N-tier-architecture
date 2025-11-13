<?php


/**
 * @method mixed prepareMenuData()
 */
interface BannerHelper{} 

class BannerHelper_Base extends Helper implements BannerHelper
{

    public function __construct(TranslatorService $translator)
    {
        $this->translator = $translator;
    }

    /**
     *
     * @param View $view El objeto View en el que inyectar
     */
    public function addBanner(View $view)
    {        
        $invitationNTier = $this->translate('banner_github_invitation');
        $invitationTree = $this->translate('banner_github_invitation_tree');
        $princeQuote = $this->translate('banner_little_prince');
        $lifeTreeQuote = $this->translate('banner_lifetree_quote'); 
    
        $nTierUrl = 'https://github.com/dCdV47/N-tier-architecture';
        $lifeTreeUrl ='https://github.com/dCdV47/lifetree';

        // Usamos HEREDOC para construir el HTML de forma limpia.
        $html = <<<HTML
        <div id="global-promo-banner-container">
        
            <!-- Banner por defecto para la demo de Arquitectura N-Tier -->
            <div id="ntier-promo-banner" class="promo-banner">
                <p>
                    <span class="quote">{$princeQuote}</span> 
                    <a href="{$nTierUrl}" target="_blank" rel="noopener noreferrer">{$invitationNTier}</a>
                </p>
            </div>

            <!-- Banner alternativo para la demo del Configurador (LifeTree.js) -->
            <div id="lifetree-promo-banner" class="promo-banner">
                <p>
                    <span class="quote">{$lifeTreeQuote}</span>
                    <a href="{$lifeTreeUrl}" target="_blank" rel="noopener noreferrer">{$invitationTree}</a>
                </p>
            </div>

        </div>
        <script type='module'>
            // 1. Comprobar si estamos en la URL correcta
            if (window.location.pathname === '/app/config') {

                // 2. Seleccionar los elementos del DOM (solo si estamos en la página correcta)
                const bannerContainer = document.getElementById('global-promo-banner-container');
                const newParent = document.querySelector('.configurator-header');

                // 3. Comprobar que ambos elementos existen antes de mover nada
                if (bannerContainer && newParent) {
                    // 4. Mover el contenedor del banner dentro del nuevo padre
                    newParent.appendChild(bannerContainer);
                    bannerContainer.style.display = 'block';
                }
            }
        </script>
        HTML;

        $view->add('injected_blocks', $html);
    
    }
}