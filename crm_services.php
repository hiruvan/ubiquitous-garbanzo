<?php

use Bitrix\Crm\Service;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;
use Bitrix\Crm\Service\Router;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Constants\Smart;

if (\Bitrix\Main\Loader::includeModule('crm')) {
    class SmartObjectController extends Service\Container
    {
        public function getRouter(): Router
        {
            return new class extends Router {
                public function parseRequest(HttpRequest $httpRequest = null): Router\ParseResult
                {
                    $result = parent::parseRequest($httpRequest);
                    if ($result->getComponentName() === 'bitrix:crm.item.details') {
                        $parameters = $result->getComponentParameters();
                        $entityTypeId = $parameters['ENTITY_TYPE_ID'] ?? $parameters['entityTypeId'] ?? null;

                        //Подмена вьюшки для проекта
                        if ((int)$entityTypeId === \Constants\Smart::ID_PROJECT && !empty($parameters['ENTITY_ID'])) {
                            if (!$this->checkEntity($parameters)) {
                                if (!$this->checkUser($parameters)) {
                                    $result = new Router\ParseResult(
                                        'mysecretcompany:item.notaccess',
                                        $parameters,
                                        $result->getTemplateName()
                                    );
                                }
                            }
                        }
                    }

                    return $result;
                }

                /**
                 * Проверяем не является ли пользователь ответственным у сущности
                 * @param array $parameters
                 * @return bool
                 */
                protected function checkEntity(array $parameters)
                {
                    global $USER;
                    $factory = Service\Container::getInstance()->getFactory($parameters['ENTITY_TYPE_ID']);
                    $item = $factory->getItem($parameters['ENTITY_ID']);
                    if (!empty($item) && (int)$item->getAssignedById() == $USER->GetID()) return true;
                    if (!empty($item) && $item->getData()['UF_CRM_2_UF_ASSISTENT'] == $USER->GetID()) return true;

                    return false;
                }

                /**
                 * Проверка на администратора или начальника отдела
                 * @return bool
                 */
                protected function checkUser(array $parameters): bool
                {
                    global $USER;
                    $factory = Service\Container::getInstance()->getFactory($parameters['ENTITY_TYPE_ID']);
                    $item = $factory->getItem($parameters['ENTITY_ID']);
                    if (empty($item)) return true;
                    if (\User\Access::checkAccess((int)$item->getAssignedById(), (int)$USER->GetID())) return true;
                    if ($item->getData()['UF_CRM_2_UF_ASSISTENT']) {
                        if (\User\Access::checkAccess($item->getData()['UF_CRM_2_UF_ASSISTENT'], (int)$USER->GetID()))
                            return true;
                    }
                    return false;
                }
            };
        }
    }
    \Bitrix\Main\DI\ServiceLocator::getInstance()->addInstance('crm.service.container', new SmartObjectController());
}