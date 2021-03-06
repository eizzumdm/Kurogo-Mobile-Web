<?php
/**
  * @package Module
  * @subpackage Links
  */

/**
  * @package Module
  * @subpackage Links
  */
class LinksModule extends Module {
  protected $id = 'links';

  protected function getModuleDefaultData() {
    return array_merge(parent::getModuleDefaultData(), array(
      'display_type' => 'springboard',
      'strings' => array(
          'description' => ''
      ),
      'links' => array()
      )
    );
  }

  protected function getSectionTitleForKey($key) {
    switch ($key) {
      case 'links': return 'Links';
      default: return parent::getSectionTitleForKey($key);
    }
  }
  
  protected function prepareAdminForSection($section, &$adminModule) {
    switch ($section) {
      case 'links':
        $adminModule->setTemplatePage('admin_links', $this->id);
        $adminModule->addInternalJavascript("/modules/{$this->id}/javascript/admin.js");
        $adminModule->addInternalCSS("/modules/{$this->id}/css/admin.css");
        $links = $this->getModuleArray('links');
        $adminModule->assign('links', $links);
        break;
      default:
        return parent::prepareAdminForSection($section, $adminModule);
        break;
    }
  }
  
  protected function initializeForPage() {
    $links = $this->getModuleArray('links');
        
    foreach ($links as $index => $link) {
      if (!is_array($link)) {
        unset($links[$index]);
      } else if (self::argVal($link, 'icon', false)) {
        $links[$index]['img'] = "/modules/{$this->id}/images/{$link['icon']}{$this->imageExt}";
      }
    }
    
    $this->assign('displayType', $this->getModuleVar('display_type'));
    $this->assign('description', $this->getModuleVar('description'));
    $this->assign('links',       $links);
  }
}
