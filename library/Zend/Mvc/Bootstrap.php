<?php
namespace Zend\Mvc;

use Zend\Di\Configuration as DiConfiguration,
    Zend\Di\Di,
    Zend\Config\Config,
    Zend\EventManager\EventManagerInterface as Events,
    Zend\EventManager\EventManager,
    Zend\EventManager\EventManagerAwareInterface,
    Zend\EventManager\EventsCapableInterface,
    Zend\Mvc\Router\Http\TreeRouteStack as Router;

class Bootstrap implements BootstrapInterface, EventManagerAwareInterface, EventsCapableInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Events
     */
    protected $events;

    /**
     * Constructor
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Set the event manager to use with this object
     *
     * @param  Events $events
     * @return void
     */
    public function setEventManager(Events $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
            'bootstrap',
        ));
        $this->events = $events;
    }

    /**
     * Retrieve the currently set event manager
     *
     * If none is initialized, an EventManager instance will be created with
     * the contexts of this class, the current class name (if extending this
     * class), and "bootstrap".
     *
     * @return Events
     */
    public function events()
    {
        if (!$this->events instanceof Events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Bootstrap the application
     *
     * - Initializes the locator, and injects it in the application
     * - Initializes the router, and injects it in the application
     * - Triggers the "bootstrap" event, passing in the application and modules
     *   as parameters. This allows module classes to perform arbitrary
     *   initialization tasks after bootstrapping but before running the
     *   application.
     *
     * @param Application $application
     * @return void
     */
    public function bootstrap(ApplicationInterface $application)
    {
        $this->setupLocator($application);
        $this->setupRouter($application);
        $this->setupView($application);
        $this->setupEvents($application);
    }


    /**
     * Sets up the locator based on the configuration provided
     *
     * @param  ApplicationInterface $application
     * @return void
     */
    protected function setupLocator(ApplicationInterface $application)
    {
        $events       = $this->events();
        $sharedEvents = $events->getSharedManager();

        $di = new Di;
        $di->instanceManager()->addTypePreference('Zend\Di\LocatorInterface', $di);
        $di->instanceManager()->addSharedInstance($sharedEvents, 'Zend\EventManager\SharedEventManager');
        $di->instanceManager()->addSharedInstance($sharedEvents, 'Zend\EventManager\SharedEventManagerInterface');

        // Default configuration for common MVC classes
        $diConfig = new DiConfiguration(array('definition' => array('class' => array(
            'Zend\Mvc\Router\RouteStackInterface' => array(
                'instantiator' => array(
                    'Zend\Mvc\Router\Http\TreeRouteStack',
                    'factory'
                ),
            ),
            'Zend\Mvc\Router\Http\TreeRouteStack' => array(
                'instantiator' => array(
                    'Zend\Mvc\Router\Http\TreeRouteStack',
                    'factory'
                ),
            ),
            'Zend\Mvc\View\DefaultRenderingStrategy' => array(
                'setLayoutTemplate' => array(
                    'layoutTemplate' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
            ),
            'Zend\Mvc\View\ExceptionStrategy' => array(
                'setDisplayExceptions' => array(
                    'displayExceptions' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
                'setExceptionTemplate' => array(
                    'exceptionTemplate' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
            ),
            'Zend\Mvc\View\RouteNotFoundStrategy' => array(
                'setDisplayNotFoundReason' => array(
                    'displayNotFoundReason' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
                'setNotFoundTemplate' => array(
                    'notFoundTemplate' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
            ),
            'Zend\View\HelperBroker' => array(
                'setClassLoader' => array(
                    'required' => true,
                    'loader'   => array(
                        'type'     => 'Zend\View\HelperLoader',
                        'required' => true,
                    ),
                ),
            ),
            'Zend\View\HelperLoader' => array(
                'registerPlugins' => array(
                    'map' => array(
                        'type'     => false,
                        'required' => false,
                    ),
                ),
            ),
            'Zend\View\Renderer\PhpRenderer' => array(
                'setBroker' => array(
                    'required' => true,
                    'broker'   => array(
                        'type'     => 'Zend\View\HelperBroker',
                        'required' => true,
                    ),
                ),
                'setCanRenderTrees' => array(
                    'required' => false,
                    'renderTrees' => array(
                        'type'     => false,
                        'required' => true,
                    ),
                ),
                'setResolver' => array(
                    'required' => false,
                    'resolver' => array(
                        'type'     => 'Zend\View\Resolver\ResolverInterface',
                        'required' => true,
                    ),
                ),
            ),
            'Zend\View\Resolver\AggregateResolver' => array(
                'attach' => array(
                    'resolver' => array(
                        'required' => false,
                        'type'     => 'Zend\View\Resolver\ResolverInterface',
                    ),
                ),
            ),
            'Zend\View\Resolver\TemplatePathStack' => array(
                'setDefaultSuffix' => array(
                    'defaultSuffix' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
                'setPaths' => array(
                    'paths' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
            ),
            'Zend\View\Strategy\PhpRendererStrategy' => array(
                'setContentPlaceholders' => array(
                    'contentPlaceholders' => array(
                        'required' => false,
                        'type'     => false,
                    ),
                ),
            ),
        )), 'instance' => array(
            'preferences' => array(
                // Use EventManager for EventManagerInterface
                'Zend\EventManager\EventManagerInterface' => 'Zend\EventManager\EventManager',
                // Use SharedEventManager for SharedEventManagerInterface
                'Zend\EventManager\SharedEventManagerInterface' => 'Zend\EventManager\SharedEventManager',
            ),
            'Zend\EventManager\EventManager' => array(
                'shared' => false, // new instance per class needing an instance
            ),
        )));
        $diConfig->configure($di);

        $config = new DiConfiguration($this->config->di);
        $config->configure($di);

        $application->setLocator($di);
    }

    /**
     * Sets up the router based on the configuration provided
     *
     * @param  Application $application
     * @return void
     */
    protected function setupRouter(ApplicationInterface $application)
    {
        $router = $application->getLocator()->get('Zend\Mvc\Router\RouteStackInterface');
        $application->setRouter($router);
    }

    /**
     * Sets up the view integration
     *
     * Pulls the View object and PhpRenderer strategy from the locator, and
     * attaches the former to the latter. Then attaches the
     * DefaultRenderingStrategy to the application event manager.
     *
     * @param  Application $application
     * @return void
     */
    protected function setupView($application)
    {
        // Basic view strategy
        $locator             = $application->getLocator();
        $events              = $application->events();
        $sharedEvents        = $locator->get('Zend\EventManager\SharedEventManagerInterface');
        $view                = $locator->get('Zend\View\View');
        $phpRendererStrategy = $locator->get('Zend\View\Strategy\PhpRendererStrategy');
        $defaultViewStrategy = $locator->get('Zend\Mvc\View\DefaultRenderingStrategy');
        $view->events()->attachAggregate($phpRendererStrategy);
        $events->attachAggregate($defaultViewStrategy);

        // Error strategies
        $noRouteStrategy   = $locator->get('Zend\Mvc\View\RouteNotFoundStrategy');
        $exceptionStrategy = $locator->get('Zend\Mvc\View\ExceptionStrategy');
        $events->attachAggregate($noRouteStrategy);
        $events->attachAggregate($exceptionStrategy);

        // Template/ViewModel listeners
        $createViewModelListener = $locator->get('Zend\Mvc\View\CreateViewModelListener');
        $injectTemplateListener  = $locator->get('Zend\Mvc\View\InjectTemplateListener');
        $injectViewModelListener = $locator->get('Zend\Mvc\View\InjectViewModelListener');
        $sharedEvents->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($createViewModelListener, 'createViewModelFromArray'), -80);
        $sharedEvents->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($noRouteStrategy, 'prepareNotFoundViewModel'), -90);
        $sharedEvents->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($createViewModelListener, 'createViewModelFromNull'), -80);
        $sharedEvents->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($injectTemplateListener, 'injectTemplate'), -90);
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($injectViewModelListener, 'injectViewModel'), -100);
        $sharedEvents->attach('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH, array($injectViewModelListener, 'injectViewModel'), -100);

        // Inject MVC Event with view model
        $mvcEvent  = $application->getMvcEvent();
        $viewModel = $mvcEvent->getViewModel();
        $viewModel->setTemplate($defaultViewStrategy->getLayoutTemplate());

        // Inject MVC Event view model as root view model
        $renderer    = $phpRendererStrategy->getRenderer();
        $modelHelper = $renderer->plugin('view_model');
        $modelHelper->setRoot($viewModel);
    }

    /**
     * Trigger the "bootstrap" event
     *
     * Triggers with the keys "application" and "config", the latter pointing
     * to the Module ManagerInterface attached to the bootstrap.
     *
     * @param  ApplicationInterface $application
     * @return void
     */
    protected function setupEvents(ApplicationInterface $application)
    {
        $application->events()->setSharedManager(
            $this->events()->getSharedManager()
        );
        $params = array(
            'application' => $application,
            'config'      => $this->config,
        );
        $this->events()->trigger(MvcEvent::EVENT_BOOTSTRAP, $this, $params);
    }
}
