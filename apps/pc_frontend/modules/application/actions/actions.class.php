<?php

/**
 * application actions.
 *
 * @package    OpenPNE
 * @subpackage saOpenSocialPlugin
 * @author     Shogo Kawahara <kawahara@tejimaya.net>
 */
class applicationActions extends sfActions
{
 /**
  * Executes canvas action
  *
  * @param sfRequest $request A request object
  */
  public function executeCanvas($request)
  {
    if (!$request->hasParameter('mid'))
    {
      return sfView::ERROR;
    }
    $this->member_app = MemberApplicationPeer::retrieveByPK($request->getParameter('mid'));
    if (empty($this->member_app))
    {
      return sfView::ERROR; 
    }
    if ($this->getUser()->getMemberId() != $this->member_app->getMemberId())
    {
      if (!$this->member_app->getIsDispOther())
      {
        return sfView::ERROR;
      }
      $request->setParameter('id', $this->member_app->getMemberId());
      sfConfig::set('sf_navi_type', 'friend');
    }
    return sfView::SUCCESS;
  }

 /**
  * Executes list action
  *
  * @param sfRequest $request A request object
  */
  public function executeList($request)
  {
    $memberId = $this->getUser()->getMemberId();
    $ownerId  = $request->hasParameter('id') ? $request->getParameter('id') : $memberId;

    $this->isOwner = false;
    $criteria = new Criteria();
    $criteria->add(MemberApplicationPeer::MEMBER_ID, $ownerId);
    $criteria->addAscendingOrderByColumn(MemberApplicationPeer::SORT_ORDER);

    if ($memberId == $ownerId)
    {
      $this->isOwner = true;
      $this->form = new AddApplicationForm();
    }
    else
    {
      $criteria->add(MemberApplicationPeer::IS_DISP_OTHER, true);
      sfConfig::set('sf_navi_type', 'friend');
    }

    $this->apps = MemberApplicationPeer::doSelect($criteria);

    if (!$request->isMethod('post'))
    {
      return sfView::SUCCESS;
    }

    $contact = $request->getParameter('contact');
    $this->form->bind($contact);
    if (!$this->form->isValid())
    {
      return sfView::SUCCESS;
    }
    $contact = $this->form->getValues();
    try
    {
      $app = ApplicationPeer::addApplication($contact['application_url'],$this->getUser()->getCulture());
    }
    catch (Exception $e)
    {
      //TODO : add error action
      return sfView::SUCCESS;
    }
    $criteria = new Criteria();
    $criteria->add(MemberApplicationPeer::MEMBER_ID,$memberId);
    $criteria->add(MemberApplicationPeer::APPLICATION_ID,$app->getId());
    $member_app = MemberApplicationPeer::doSelectOne($criteria);
    if (!empty($member_app))
    {
      return $this->redirect('application/canvas?mid='.$member_app->getId());
    }
    $member_app = new MemberApplication();
    $member_app->setMemberId($memberId);
    $member_app->setApplicationId($app->getId());
    $member_app->setIsDispOther(true);
    $member_app->setIsDispHome(true);
    $member_app->save();
    return $this->redirect('application/canvas?mid='.$member_app->getId());
  }

 /**
  * Executes setting action
  *
  * @param sfRequest $request A request object
  */
  public function executeSetting($request)
  {
    if (!$request->hasParameter('mid'))
    {
      return sfView::ERROR;
    }

    $this->applicationSettingForm = new ApplicationSettingForm();
    $memberId = $this->getUser()->getMember()->getId();
    $modId = $request->getParameter('mid');
    $this->applicationSettingForm->setConfigWidgets($memberId,$modId);

    $memberApp = MemberApplicationPeer::retrieveByPk($modId);
    $this->appName = $memberApp->getApplication()->getTitle();

    $this->memberApplicationSettingForm = new MemberApplicationSettingForm();
    $isDispOther = $memberApp->getIsDispOther();
    $isDispHome  = $memberApp->getIsDispHome();
        $this->memberApplicationSettingForm->setDefaults(array(
      'is_disp_other' => $isDispOther,
      'is_disp_home'  => $isDispHome,
    ));

    if (!$request->isMethod('post'))
    {
      return sfView::SUCCESS;
    }
    
    $this->memberApplicationSettingForm->bind($request->getParameter('member_app_setting'));
    $this->applicationSettingForm->bind($request->getParameter('setting'));

    if ($this->applicationSettingForm->isValid() && $this->memberApplicationSettingForm->isValid())
    {
      $this->applicationSettingForm->save($modId);
      $this->memberApplicationSettingForm->save($modId);
      $this->redirect('application/canvas?mid='.$modId);
    }
    return sfView::SUCCESS;
  }

 /**
  * Executes gallery action
  *
  * @param sfRequest $request A request object
  */
  public function executeGallery($request)
  {
    $criteria = new Criteria();
    $criteria->addDescendingOrderByColumn(ApplicationPeer::ID);
    $this->pager = new sfPropelPager('Application', 10);
    $this->pager->setCriteria($criteria);
    $this->pager->setPage($request->getParameter('page',1));
    $this->pager->init();

    return sfView::SUCCESS;
  }

 /**
  * Executes js action
  *
  * @param sfRequest $request A request object
  */
  public function executeJs($request)
  {
    $response = $this->getResponse();
    $response->setContentType('text/javascript');
    return sfView::SUCCESS;
  }

 /**
  * Executes sort application
  * 
  * @param sfRequest $request A request object
  */
  public function executeSortApplication($request)
  {
    if ($this->getRequest()->isXmlHttpRequest())
    {
      $memberId = $this->getUser()->getMember()->getId();
      $order = $request->getParameter('order');
      for ($i = 0; $i < count($order); $i++)
      {
        $memberApp = MemberApplicationPeer::retrieveByPk($order[$i]);
        if ($memberApp && $memberApp->getMemberId() == $memberId)
        {
          $memberApp->setSortOrder($i);
          $memberApp->save();
        }
      }
    }
    return sfView::NONE;
  }
}
