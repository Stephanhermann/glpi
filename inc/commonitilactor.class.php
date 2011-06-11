<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Remi Collet
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Class Ticket_User
abstract class CommonITILActor extends CommonDBRelation {

   // From CommonDBRelation
   // itemtype_1 : always ITIL object
//    public $itemtype_1 = 'Ticket';
//    public $items_id_1 = 'tickets_id';
   // itemtype_1 : always actor object
//    public $itemtype_2 = 'User';
//    public $items_id_2 = 'users_id';

   var $checks_and_logs_only_for_itemtype1 = true;

   var $no_form_page = true;

   function getActorForeignKey() {
      return $this->items_id_2;
   }


   function getItilObjectForeignKey() {
      return $this->items_id_1;
   }


   function getActors($items_id) {
      global $DB;

      $users = array();
      $query = "SELECT `".$this->getTable()."`.*
                FROM `".$this->getTable()."`
                WHERE `".$this->getItilObjectForeignKey()."` = '$items_id'";

      foreach ($DB->request($query) as $data) {
         $users[$data['type']][$data[$this->getActorForeignKey()]] = $data;
      }
      return $users;
   }


   function canUpdateItem() {

      return (parent::canUpdateItem()
              || (isset($this->fields['users_id'])
                  && $this->fields['users_id'] == getLoginUserID()));
   }


   /**
    * Check right on an item - overloaded to check user access to its datas
    *
    * @param $ID ID of the item (-1 if new item)
    * @param $right Right to check : r / w / recursive
    * @param $input array of input data (used for adding item)
    *
    * @return boolean
   **/
   function can($ID, $right, &$input=NULL) {

      if ($ID>0) {
         if (isset($this->fields['users_id']) && $this->fields['users_id']===getLoginUserID()) {
            return true;
         }
      }
      return parent::can($ID, $right, $input);
   }


   /**
    * Print the object user form for notification
    *
    * @param $ID integer ID of the item
    * @param $options array
    *
    * @return Nothing (display)
   **/
   function showUserNotificationForm($ID, $options=array()) {
      global $CFG_GLPI,$LANG;

      $this->check($ID,'w');

      if (!isset($this->fields['users_id'])) {
         return false;
      }
      $item = new $this->itemtype_1();

      echo "<br><form method='post' action='".$CFG_GLPI['root_doc']."/front/popup.php'>";
      echo "<div class='center'>";
      echo "<table class='tab_cadre'>";
      echo "<tr class='tab_bg_2'><td>".$item->getTypeName()."&nbsp;:</td>";
      echo "<td>";
      if ($item->getFromDB($this->fields[$this->getItilObjectForeignKey()])) {
         echo $item->getField('name');
      }
      echo "</td></tr>";

      $user  = new User();
      $email = "";
      if ($user->getFromDB($this->fields["users_id"])) {
         $email = $user->getField('email');
      }

      echo "<tr class='tab_bg_2'><td>".$LANG['common'][34]."&nbsp;:</td>";
      echo "<td>".$user->getName()."</td></tr>";

      echo "<tr class='tab_bg_1'><td>".$LANG['job'][19]."&nbsp;:</td>";
      echo "<td>";
      Dropdown::showYesNo('use_notification', $this->fields['use_notification']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".$LANG['mailing'][118]."&nbsp;:</td>";
      echo "<td>";
      if (!empty($email) && NotificationMail::isUserAddressValid($email)) {
         echo $email;
      } else {
         echo "<input type='text' size='40' name='alternative_email' value='".
                $this->fields['alternative_email']."'>";
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td class='center' colspan='2'>";
      echo "<input type='submit' name='update' value=\"".$LANG['buttons'][7]."\" class='submit'>";
      echo "<input type='hidden' name='id' value='$ID'>";
      echo "</td></tr>";

      echo "</table></div></form>";
   }


   function post_deleteFromDB() {
      global $CFG_GLPI;

      $donotif = $CFG_GLPI["use_mailing"];

      if (isset($this->input["_no_notif"]) && $this->input["_no_notif"]) {
         $donotif = false;
      }

      $item = new $this->itemtype_1();

      if ($item->getFromDB($this->fields[$this->getItilObjectForeignKey()])) {
         if ($item->fields["suppliers_id_assign"] == 0
             && $item->countUsers(CommonITILObject::ASSIGN) == 0
             && $item->countGroups(CommonITILObject::ASSIGN) == 0) {

            $item->update(array('id'     => $this->fields[$this->getItilObjectForeignKey()],
                                'status' => 'new'));
         } else {
            $item->updateDateMod($this->fields[$this->getItilObjectForeignKey()]);

            if ($donotif) {
               $options = array();
               if (isset($this->fields['users_id'])) {
                  $options = array('_old_user' => $this->fields);
               }
               NotificationEvent::raiseEvent("update", $item, $options);
            }
         }

      }
      parent::post_deleteFromDB();
   }


   function post_addItem() {

      $item = new $this->itemtype_1();

      $no_stat_computation = true;
      if ($this->input['type']==CommonITILObject::ASSIGN) {
         $no_stat_computation = false;
      }
      $item->updateDateMod($this->fields[$this->getItilObjectForeignKey()], $no_stat_computation);

      parent::post_addItem();
   }

}

?>
