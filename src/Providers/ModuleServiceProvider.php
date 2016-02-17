<?php

namespace KodiCMS\Datasource\Providers;

use Event;
use KodiCMS\Navigation\Page;
use KodiCMS\Navigation\Section;
use Yajra\Datatables\Datatables;
use KodiCMS\Navigation\Navigation;
use KodiCMS\Support\ServiceProvider;
use KodiCMS\Datasource\FieldManager;
use KodiCMS\Datasource\FieldGroupManager;
use KodiCMS\Datasource\DatasourceManager;
use KodiCMS\Datasource\Model\SectionFolder;
use KodiCMS\Datasource\Console\Commands\DatasourceMigrate;
use KodiCMS\Datasource\Facades\FieldManager as FieldManagerFacade;
use KodiCMS\Datasource\Facades\FieldGroupManager as FieldGroupManagerFacade;
use KodiCMS\Datasource\Facades\DatasourceManager as DatasourceManagerFacade;

class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerAliases([
            'DatasourceManager' => DatasourceManagerFacade::class,
            'FieldManager'      => FieldManagerFacade::class,
            'Datatables'        => Datatables::class,
            'FieldGroupManager' => FieldGroupManagerFacade::class
        ]);

        $this->app->singleton('datasource.manager', function () {
            return new DatasourceManager(config('datasources', []));
        });

        $this->app->singleton('datasource.field.manager', function () {
            return new FieldManager(config('fields', []));
        });

        $this->app->singleton('datasource.group.manager', function () {
            return new FieldGroupManager(config('field_groups', []));
        });

        $this->registerConsoleCommand(DatasourceMigrate::class);
    }

    public function boot()
    {
        $this->initNavigation();
    }

    protected function initNavigation()
    {
        Event::listen('navigation.inited', function (Navigation $navigation) {
            if (! is_null($section = $navigation->findSectionOrCreate('Datasources'))) {
                $sections = app('datasource.manager')->getRootSections();

                foreach ($sections as $dsSection) {
                    $page = new Page([
                        'name'     => $dsSection->getName(),
                        'label'    => $dsSection->getName(),
                        'icon'     => $dsSection->getIcon(),
                        'url'      => $dsSection->getLink(),
                        'priority' => $dsSection->getMenuPosition(),
                    ]);

                    if ($dsSection->getSetting('show_in_root_menu')) {
                        $navigation->getRootSection()->addPage($page);
                    } else {
                        $section->addPage($page);
                    }
                }

                $folders = SectionFolder::with('sections')->get();

                foreach ($folders as $folder) {
                    if (count($folder->sections) > 0) {
                        $subSection = new Section($navigation, [
                            'name'  => 'Datasource',
                            'label' => $folder->name,
                            'icon'  => 'folder-open-o',
                        ]);

                        foreach ($folder->sections as $dsSection) {
                            $subSection->addPage(new Page([
                                'name'  => $dsSection->getName(),
                                'label' => $dsSection->getName(),
                                'icon'  => $dsSection->getIcon(),
                                'url'   => $dsSection->getLink(),
                            ]));
                        }

                        $section->addPage($subSection);
                    }
                }

                $types = app('datasource.manager')->getAvailableTypes();

                $subSection = new Section($navigation, [
                    'name'  => 'Datasource',
                    'label' => trans('datasource::core.button.create'),
                    'icon'  => 'plus',
                ]);

                foreach ($types as $type => $object) {
                    $subSection->addPage(new Page([
                        'name'  => $object->getTitle(),
                        'label' => $object->getTitle(),
                        'icon'  => $object->getIcon(),
                        'url'   => $object->getLink(),
                    ]));
                }

                $section->addPage($subSection);
            }
        });
    }
}
