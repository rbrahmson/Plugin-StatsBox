<?php if(!defined('APPLICATION')) exit();
/* 	Copyright 2013 Zachary Doll
 * 	This program is free software: you can redistribute it and/or modify
 * 	it under the terms of the GNU General Public License as published by
 * 	the Free Software Foundation, either version 3 of the License, or
 * 	(at your option) any later version.
 *
 * 	This program is distributed in the hope that it will be useful,
 * 	but WITHOUT ANY WARRANTY; without even the implied warranty of
 * 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * 	GNU General Public License for more details.
 *
 * 	You should have received a copy of the GNU General Public License
 * 	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
$PluginInfo['StatsBox'] = array(
  'Name' => 'Stats Box',
  'Description' => 'Adds a stats box to the discussions list that shows the total comments, views, and follows. Easily change what shows through simple settings. Inspired by Voting by Mark O\'Sullivan.',
  'Version' => '1.5',
  'RequiredApplications' => array('Vanilla' => '2.0.18.8'),
  'RequiredTheme' => FALSE,
  'RequiredPlugins' => FALSE,
  'MobileFriendly' => FALSE,
  'HasLocale' => TRUE,
  'RegisterPermissions' => FALSE,
  'SettingsUrl' => '/settings/statsbox',
  'SettingsPermission' => 'Garden.Settings.Manage',
  'Author' => 'Zachary Doll',
  'AuthorEmail' => 'hgtonight@daklutz.com',
  'AuthorUrl' => 'http://www.daklutz.com',
  'License' => 'GPLv3'
);

class StatsBoxPlugin extends Gdn_Plugin {

  public function CategoriesController_Render_Before($Sender) {
    $this->_AddResources($Sender);
  }

  public function DiscussionsController_Render_Before($Sender) {
    $this->_AddResources($Sender);
  }
  
  public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
    $this->_RenderStatsBox($Sender);
  }

  public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
    $this->_RenderStatsBox($Sender);
  }

  public function SettingsController_StatsBox_Create($Sender) {
    $Sender->Permission('Garden.Settings.Manage');
    $this->_AddResources($Sender, TRUE);

    $Validation = new Gdn_Validation();
    $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
    $ConfigurationModel->SetField(array(
        'Plugins.StatsBox.HideComments',
        'Plugins.StatsBox.HideViews',
        'Plugins.StatsBox.HideFollows',
        'Plugins.StatsBox.DisableCSS',
    ));
    $Sender->Form->SetModel($ConfigurationModel);

    if($Sender->Form->AuthenticatedPostBack() === FALSE) {
      $Sender->Form->SetData($ConfigurationModel->Data);
    }
    else {
      $Data = $Sender->Form->FormValues();
      if($Sender->Form->Save() !== FALSE) {
        $Sender->InformMessage('<span class="InformSprite Sliders"></span>' . T('Your changes have been saved.'), 'HasSprite');
      }
    }

    $Sender->AddSideMenu();
    $Sender->Title($this->GetPluginName() . ' ' . T('Settings'));
    $Sender->Render($this->GetView('settings.php'));
  }

  public function Base_GetAppSettingsMenuItems_Handler($Sender) {
    $Menu = &$Sender->EventArguments['SideMenu'];
    $Menu->AddLink('Add-ons', 'Stats Box', 'settings/statsbox', 'Garden.Settings.Manage');
  }

  private function _AddResources($Sender, $Forced = FALSE) {
    if(C('Plugins.StatsBox.DisableCSS', FALSE) == FALSE || $Forced) {
      $Sender->AddCSSFile($this->GetResource('design/statsbox.css', FALSE, FALSE));
    }
  }

  private function _RenderStatsBox($Sender) {
    $Discussion = GetValue('Discussion', $Sender->EventArguments);
    $String = '';

    if(C('Plugins.StatsBox.HideComments', FALSE) == FALSE) {
      $String .= Wrap(
              Wrap(T('Comments')) . Gdn_Format::BigNumber($Discussion->CountComments), 'span', array('class' => 'StatsBox AnswersBox'));
    }

    if(C('Plugins.StatsBox.HideViews', FALSE) == FALSE) {
      $String .= Wrap(
              Wrap(T('Views')) . Gdn_Format::BigNumber($Discussion->CountViews), 'span', array('class' => 'StatsBox ViewsBox'));
    }


    if(C('Plugins.StatsBox.HideFollows', FALSE) == FALSE) {
      if(!is_numeric($Discussion->CountBookmarks)) {
        $Discussion->CountBookmarks = 0;
      }
      $BookmarkAction = T($Discussion->Bookmarked == '1' ? 'Unbookmark' : 'Bookmark');
      $Session = Gdn::Session();
      if($Session->IsValid()) {
        $String .= Wrap(
                Anchor(
                        Wrap(T('Follows')) . Gdn_Format::BigNumber($Discussion->CountBookmarks), '/vanilla/discussion/bookmark/' . $Discussion->DiscussionID . '/' . $Session->TransientKey() . '?Target=' . urlencode($Sender->SelfUrl), '', array('title' => $BookmarkAction)
                ), 'span', array('class' => 'StatsBox FollowsBox'));
      }
      else {
        $String .= Wrap(
                Wrap(T('Follows')) . $Discussion->CountBookmarks, 'span', array('class' => 'StatsBox FollowsBox'));
      }
    }

    echo $String;
  }

  public function Setup() {
	$this->Structure();
    return TRUE;
  }
///////////////////////////////////////// 
   public function Structure() {
	//Database structure/updates
		if(version_compare(APPLICATION_VERSION, '2.2', '<')) return;	//No structure change for 2.1
		$Msg = 'Statsbox Plugin: '.t('A one time database update is taking place during plugin setup for Vanilla Version ').APPLICATION_VERSION;
		Gdn::controller()->informMessage($Msg); 
		echo wrap(wrap($Msg,'H3'),'BR');
		$prefix = Gdn::database()->DatabasePrefix;
		$sql = "UPDATE ".$prefix."Discussion d, 
					(SELECT ud.DiscussionID, SUM(ud.Bookmarked) AS 'CountBookmarked' 
					FROM ".$prefix."UserDiscussion ud
					GROUP BY ud.DiscussionID
					) AS ud
					SET d.CountBookmarks = ud.CountBookmarked
					WHERE d.DiscussionID = ud.DiscussionID";
		$result = Gdn::sql()->query($sql);
		$Msg = 'Statsbox Plugin: '.t('The one time database update completed ');
		Gdn::controller()->informMessage($Msg); 
		echo wrap(wrap($Msg,'H3'),'BR');
   }
 /////////////////////////////////////////
// As of Vanilla 2.2 the discussion table bookmark count is not updated internally so we do it here
  public function DiscussionModel_AfterBookmark_Handler($Sender,$Args) {
	if(version_compare(APPLICATION_VERSION, '2.2', '<')) return;	//This hook should only exists in 2.2 and above...
	$Discussion = $Sender->EventArguments['Discussion'];
	$Sender->SQL->update('Discussion')
		  ->set('CountBookmarks', $Sender->BookmarkCount($Discussion->DiscussionID))
		  ->where('DiscussionID', $DiscussionID)
		  ->put();
	return;
  }

}