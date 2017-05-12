<?php
// src/Stsbl/InternetBundle/Crud/NacCrud.php
namespace Stsbl\InternetBundle\Crud;

use IServ\CoreBundle\Entity\Specification\PropertyMatchSpecification;
use IServ\CoreBundle\Service\Config;
use IServ\CoreBundle\Service\Logger;
use IServ\CrudBundle\Crud\AbstractCrud;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\ListMapper;
use Stsbl\InternetBundle\Entity\Nac;
use Stsbl\InternetBundle\Security\Privilege;
use Stsbl\InternetBundle\Service\NacManager;
use Stsbl\InternetBundle\Twig\Extension\Time;
use Symfony\Component\Security\Core\User\UserInterface;

/*
 * The MIT License
 *
 * Copyright 2017 Felix Jacobi.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class NacCrud extends AbstractCrud
{

    /**
     * @var \IServ\CoreBundle\Service\Logger
     */
    private $logger;
    
    /**
     * @var Time
     */
    private $time;
    
    /**
     * @var NacManager
     */
    private $manager;
    
    /**
     * @var Config
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->title = _('Manage NACs');
        $this->itemTitle = 'NAC';
        $this->routesPrefix = 'internet/manage/';
        $this->routesNamePrefix = 'internet_manage_';
        //$this->options['help'] = 'v3/modules/network/print/';
        $this->templates['crud_index'] = 'StsblInternetBundle:Nac:index.html.twig';
    }

    /**
     * {@inheritdoc}
     */
    public function prepareBreadcrumbs()
    {
        return array(_('Internet') => $this->router->generate('internet_index'));
    }

    /**
     * {@inheritdoc}
     */
    protected function buildRoutes()
    {
        parent::buildRoutes();

        $this->routes['index']['_controller'] = 'StsblInternetBundle:Nac:index';
    }
    
    /**
     * {@inheritdoc}
     */
    public function postRemove(CrudInterface $nac)
    {
        // Log deletion of NACs
        /* @var $nac Nac */
        $value = $this->time->intervalToString($nac->getRemain());
        if ($nac->getUser() === null) {
            $msg = sprintf('NAC "%s" mit %s verbleibender Zeit erstellt von "%s" gelöscht', $nac->getId(), $value, $nac->getOwner()->getName());
        }
        else {
            $msg = sprintf('NAC "%s" mit %s verbleibender Zeit erstellt von "%s" und vergeben an "%s" gelöscht', $nac->getId(), $value, $nac->getOwner()->getName(), $nac->getUser()->getName());
        }
        $this->logger->write($msg, null, 'Internet');
        
        // run inet_timer to disable deleted NACs
        $this->manager->inetTimer();
    }

    /**
     * {@inheritdoc}
     */
    public function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('nac', null, array('label' => _('NAC'), 'responsive' => 'all'))
            ->add('owner', 'entity', array('label' => _('Created by'), 'responsive' => 'desktop'))
            ->add('created', 'datetime', array('label' => _('Created on'), 'responsive' => 'desktop'))
            ->add('remain', null, array(
                'template' => 'StsblInternetBundle:List:field_interval.html.twig',
                'label' => _('Remaining'),
                'responsive' => 'min-mobile',
            ))
            ->add('user', 'entity', array('label' => _('Assigned to'), 'responsive' => 'all'))
            ->add('assigned', 'datetime', array('label' => _('Assigned on'), 'responsive' => 'desktop'))
            ->add('ip', null, array(
                'template' => 'StsblInternetBundle:List:field_status.html.twig',
                'label' => _('Status'), 
                'responsive' => 'desktop'))
        ;
    }
    
    /*** SETTERS ***/
    
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    public function setTwigTimeExtension(Time $time)
    {
        $this->time = $time;
    }
    
    public function setManager(NacManager $manager)
    {
        $this->manager = $manager;
    }
    
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /*** SECURITY ***/

    public function isAuthorized()
    {
        return $this->isGranted(Privilege::INET_NACS) && $this->config->get('Activation');
    }

    public function isAllowedToView(CrudInterface $object = null, UserInterface $user = null)
    {
        return false;
    }

    public function isAllowedToAdd(UserInterface $user = null)
    {
        return false;
    }

    public function isAllowedToEdit(CrudInterface $object = null, UserInterface $user = null)
    {
        return false;
    }
    
    public function getFilterSpecification() 
    {
        // no filtering for admins
        if ($this->getUser()->isAdmin()) {
            return;
        }
        
        return new PropertyMatchSpecification('owner', $this->getUser()->getUsername());
    }

}
