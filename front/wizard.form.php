<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Metademands plugin for GLPI
 Copyright (C) 2018-2019 by the Metademands Development Team.

 https://github.com/InfotelGLPI/metademands
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Metademands.

 Metademands is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Metademands is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Metademands. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');
Session::checkLoginUser();

$wizard      = new PluginMetademandsWizard();
$metademands = new PluginMetademandsMetademand();
$field       = new PluginMetademandsField();

if (empty($_POST['metademands_id'])) {
   $_POST['metademands_id'] = 0;
}

if (empty($_GET['metademands_id'])) {
   $_GET['metademands_id'] = 0;
}

if (empty($_GET['tickets_id'])) {
   $_GET['tickets_id'] = 0;
}

if (empty($_GET['resources_id'])) {
   $_GET['resources_id'] = 0;
}

if (empty($_GET['resources_step'])) {
   $_GET['resources_step'] = '';
}

if (empty($_GET['step'])) {
   $_GET['step'] = PluginMetademandsMetademand::STEP_LIST;
}

// Url Redirect case
if (isset($_GET['id'])) {
   $_GET['metademands_id'] = $_GET['id'];
   $_GET['step']           = PluginMetademandsMetademand::STEP_SHOW;
   $_GET['tickets_id']     = "0";
}

if (isset($_POST['next'])) {
   $KO              = false;
   $onlybasketdatas = false;
   $step            = $_POST['step'] + 1;
   if (isset($_POST['update_fields'])) {
      if ($metademands->canCreate()
          || PluginMetademandsGroup::isUserHaveRight($_POST['form_metademands_id'])) {

         $data  = $field->find(['plugin_metademands_metademands_id' => $_POST['form_metademands_id']]);
         $metademands->getFromDB($_POST['form_metademands_id']);
         $plugin = new Plugin();
         if ($plugin->isActivated('orderprojects')
             && $metademands->fields['is_order'] == 1) {

            $onlybasketdatas = true;
            $orderprojects   = new PluginOrderprojectsMetademand();
            $orderprojects->createFromMetademands($_POST);

         } else {

            $nblines = 0;
            //Create ticket
            if ($metademands->fields['is_order'] == 1) {
               $basketline   = new PluginMetademandsBasketline();
               $basketToSend = $basketline->find(['plugin_metademands_metademands_id' => $_POST['form_metademands_id'],
                                                  'users_id'                          => Session::getLoginUserID()]);

               $basketLines = [];
               foreach ($basketToSend as $basketLine) {
                  $basketLines[$basketLine['line']][] = $basketLine;
               }

               $basket = [];
               if (count($basketLines) > 0) {
                  foreach ($basketLines as $idline => $field) {
                     foreach ($field as $k => $v) {
                        $basket[$v['plugin_metademands_fields_id']] = $v['value'];
                     }

                     $_POST['basket'][$nblines] = $basket;
                     $nblines++;
                  }
                  $_POST['field'] = $basket;

                  $basketline->deleteByCriteria(['plugin_metademands_metademands_id' => $_POST['form_metademands_id'],
                                                 'users_id'                          => Session::getLoginUserID()]);
               } else {
                  $KO = true;
                  Session::addMessageAfterRedirect(__("There is no line on the basket", "metademands"), false, ERROR);
               }
            }
            if ($nblines == 0) {
               $post = $_POST['field'];
               $nblines = 1;
            }

            for ($i = 0; $i < $nblines; $i++) {

               if ($metademands->fields['is_order'] == 1) {
                  $post = $_POST['basket'][$i];
               }

               foreach ($data as $id => $value) {
                  if (is_array(PluginMetademandsField::_unserialize($value['check_value']))) {
                     if ($value['type'] == 'datetime_interval'
                        && !isset($value['second_date_ok'])) {
                        $value['second_date_ok'] = true;
                        $value['id'] = $id . '-2';
                        $value['label'] = $value['label2'];
                        $data[$id . '-2'] = $value;
                     }
                     // Check if no form values block the creation of meta
                     $metademandtasks_tasks_id = PluginMetademandsMetademandTask::getSonMetademandTaskId($_POST['form_metademands_id']);

                     if (!is_null($metademandtasks_tasks_id)) {
                        $_SESSION['son_meta'] = $metademandtasks_tasks_id;
                        if (!isset($_POST['field'])) {
                           $_POST['field'][$id] = 0;
                        }
                        if (isset($_POST['radio'][$id])) {
                           $_POST['field'][$id] = $_POST['radio'][$id];
                        }
                        foreach (PluginMetademandsField::_unserialize($value['check_value']) as $keyId => $check_value) {
                           $plugin_metademands_tasks_id = PluginMetademandsField::_unserialize($value['plugin_metademands_tasks_id']);
                           $this->checkValueOk($check_value, $plugin_metademands_tasks_id[$keyId], $metademandtasks_tasks_id, $id, $value);
                        }
                     }
                     foreach (PluginMetademandsField::_unserialize($value['check_value']) as $keyId => $check_value) {
                        $value['check_value'] = $check_value;
                        $value['plugin_metademands_tasks_id'] = PluginMetademandsField::_unserialize($value['plugin_metademands_tasks_id'])[$keyId];
                        $value['fields_link'] = isset(PluginMetademandsField::_unserialize($value['fields_link'])[$keyId]) ? PluginMetademandsField::_unserialize($value['fields_link'])[$keyId] : 0;
                        if (isset($_POST['field'][$id])) {
                           if (!$wizard->checkMandatoryFields($value, ['id' => $id, 'value' => $_POST['field'][$id]], $_POST['field'])) {
                              foreach ($_POST['field'] as $key => $field) {
                                 $field = str_replace('\r\n', '&#x0A;', $field);
                                 $_POST['field'][$key] = $field;
                              }
                              $KO = true;
                           }

                           if ($value == 'checkbox') {// Checkbox
                              $_SESSION['plugin_metademands']['fields'][$id] = 1;
                           } else {// Other fields
                              if (is_array($_POST['field'][$id])) {
                                 $_POST['field'][$id] = PluginMetademandsField::_serialize($_POST['field'][$id]);
                              }
                              $_SESSION['plugin_metademands']['fields'][$id] = $_POST['field'][$id];
                           }

                        } else if ($value['type'] == 'checkbox') {
                           if (!isset($_POST['field'])
                              || (isset($_POST['field']) && $wizard->checkMandatoryFields($value, ['id' => $id, 'value' => ''], $_POST['field']))) {
                              $_SESSION['plugin_metademands']['fields'][$id] = '';
                           } else {
                              $KO = true;
                           }
                        } else if ($value['type'] == 'radio') {
                           if ($value['is_mandatory'] == 1) {
                              if (isset($_POST['radio'])
                                 && $wizard->checkMandatoryFields($value, ['id' => $id, 'value' => $_POST['radio'][$id]])) {
                                 $_SESSION['plugin_metademands']['fields'][$id] = $_POST['radio'][$id];
                              } else {
                                 $KO = true;
                              }
                           } else if (isset($_POST['radio'][$id])) {
                              $_SESSION['plugin_metademands']['fields'][$id] = $_POST['radio'][$id];
                           }

                           // Check if no form values block the creation of meta
                           $metademandtasks_tasks_id = PluginMetademandsMetademandTask::getSonMetademandTaskId($_POST['form_metademands_id']);
                           if (isset($_POST['radio'][$id]) &&
                              is_array($metademandtasks_tasks_id) &&
                              in_array($value['plugin_metademands_tasks_id'], $metademandtasks_tasks_id) &&
                              !PluginMetademandsTicket_Field::isCheckValueOK($_POST['radio'][$id], $value['check_value'], $value['type'])) {
                              //                   $step++;
                              $metademandToHide = array_keys($metademandtasks_tasks_id, $value['plugin_metademands_tasks_id']);
                              $_SESSION['metademands_hide'][$metademandToHide[0]] = $metademandToHide[0];

                           }
                        } else if ($value['type'] == 'upload') {
                           if (!$wizard->checkMandatoryFields($value, ['id' => $id, 'value' => 1])) {
                              $KO = true;
                           } else {
                              $files = json_decode($post[$id], 1);
                              $filename = [];
                              $prefixname = [];
                              $tagname = [];
                              foreach ($files as $file) {
                                 $filename[] = $file['_filename'];
                                 $prefixname[] = $file['_prefix_filename'];
                                 $tagname[] = $file['_tag_filename'];
                              }
                              $_POST['_filename'] = $filename;
                              $_POST['_prefix_filename'] = $prefixname;
                              $_POST['_tag_filename'] = $tagname;
                              $_SESSION['plugin_metademands']['fields']['_filename'] = $_POST['_filename'];
                              $_SESSION['plugin_metademands']['fields']['_prefix_filename'] = $_POST['_prefix_filename'];
                           }
                        }
                     }
                  } else {
                     $KO = false;
                     if ($value['type'] == 'datetime_interval' && !isset($value['second_date_ok'])) {
                        $value['second_date_ok'] = true;
                        $value['id'] = $id . '-2';
                        $value['label'] = $value['label2'];
                        $data[$id . '-2'] = $value;
                     }
                     // Check if no form values block the creation of meta
                     $metademandtasks_tasks_id = PluginMetademandsMetademandTask::getSonMetademandTaskId($_POST['form_metademands_id']);

                     if (!is_null($metademandtasks_tasks_id)) {
                        $_SESSION['son_meta'] = $metademandtasks_tasks_id;
                        if (!isset($_POST['field'])) {
                           $_POST['field'][$id] = 0;
                        }
                        if (isset($_POST['radio'][$id])) {
                           $_POST['field'][$id] = $_POST['radio'][$id];
                        }
                        $this->checkValueOk($value['check_value'], $value['plugin_metademands_tasks_id'], $metademandtasks_tasks_id, $id, $value);
                     }
                     if (isset($_POST['field'][$id])) {
                        if (!$wizard->checkMandatoryFields($value, ['id' => $id, 'value' => $_POST['field'][$id]], $_POST['field'])) {
                           foreach ($_POST['field'] as $key => $field) {
                              $field = str_replace('\r\n', '&#x0A;', $field);
                              $_POST['field'][$key] = $field;
                           }
                           $KO = true;
                        }

                        if ($value == 'checkbox') {// Checkbox
                           $_SESSION['plugin_metademands']['fields'][$id] = 1;
                        } else {// Other fields
                           if (is_array($_POST['field'][$id])) {
                              $_POST['field'][$id] = PluginMetademandsField::_serialize($_POST['field'][$id]);
                           }
                           $_SESSION['plugin_metademands']['fields'][$id] = $_POST['field'][$id];
                        }

                     } else if ($value['type'] == 'checkbox') {
                        if (!isset($_POST['field'])
                           || (isset($_POST['field']) && $wizard->checkMandatoryFields($value, ['id' => $id, 'value' => ''], $_POST['field']))) {
                           $_SESSION['plugin_metademands']['fields'][$id] = '';
                        } else {
                           $KO = true;
                        }
                     } else if ($value['type'] == 'radio') {
                        if ($value['is_mandatory'] == 1) {
                           if (isset($_POST['radio'])
                              && $wizard->checkMandatoryFields($value, ['id' => $id, 'value' => $_POST['radio'][$id]])) {
                              $_SESSION['plugin_metademands']['fields'][$id] = $_POST['radio'][$id];
                           } else {
                              $KO = true;
                           }
                        } else if (isset($_POST['radio'][$id])) {
                           $_SESSION['plugin_metademands']['fields'][$id] = $_POST['radio'][$id];
                        }

                        // Check if no form values block the creation of meta
                        $metademandtasks_tasks_id = PluginMetademandsMetademandTask::getSonMetademandTaskId($_POST['form_metademands_id']);
                        if (isset($_POST['radio'][$id]) &&
                           is_array($metademandtasks_tasks_id) &&
                           in_array($value['plugin_metademands_tasks_id'], $metademandtasks_tasks_id) &&
                           !PluginMetademandsTicket_Field::isCheckValueOK($_POST['radio'][$id], $value['check_value'], $value['type'])) {
                           //                   $step++;
                           $metademandToHide = array_keys($metademandtasks_tasks_id, $value['plugin_metademands_tasks_id']);
                           $_SESSION['metademands_hide'][$metademandToHide[0]] = $metademandToHide[0];
                        }
                     } else if ($value['type'] == 'upload') {
                        if (!$wizard->checkMandatoryFields($value, ['id' => $id, 'value' => 1])) {
                           $KO = true;
                        } else {
                           if (isset($_POST['_filename'])) {
                              $_SESSION['plugin_metademands']['fields']['_filename'] = $_POST['_filename'];
                           }
                           if (isset($_POST['_prefix_filename'])) {
                              $_SESSION['plugin_metademands']['fields']['_prefix_filename'] = $_POST['_prefix_filename'];
                           }
                        }
                     }
                  }

                  $metademands->getFromDB($_POST['form_metademands_id']);
                  $ticketfields_data = $metademands->formatTicketFields($_POST['form_metademands_id'], $metademands->getField('tickettemplates_id'));
                  if (count($ticketfields_data)) {
                     if (!isset($ticketfields_data['entities_id'])) {
                        $ticketfields_data['entities_id'] = $_SESSION['glpiactive_entity'];
                     }
                     $ticketfields_data['itilcategories_id'] = $metademands->fields['itilcategories_id'];
                     $tickettasks = new PluginMetademandsTicketTask();
                     if (!$tickettasks->isMandatoryField($ticketfields_data, true, false, __('Mandatory fields of the metademand ticket must be configured', 'metademands'))) {
                        $KO = true;
                     }
                  }

                  // Save requester user
                  $_SESSION['plugin_metademands']['fields']['_users_id_requester'] = $_POST['_users_id_requester'];
                  // Case of simple ticket convertion
                  $_SESSION['plugin_metademands']['fields']['tickets_id'] = $_POST['tickets_id'];
                  // Resources id
                  $_SESSION['plugin_metademands']['fields']['resources_id'] = $_POST['resources_id'];
                  // Resources step
                  $_SESSION['plugin_metademands']['fields']['resources_step'] = $_POST['resources_step'];


                  // FILE UPLOAD
                  if (isset($_FILES['filename']['tmp_name'])) {
                     if (!isset($_SESSION['plugin_metademands']['files'][$_POST['form_metademands_id']])) {
                        foreach ($_FILES['filename']['tmp_name'] as $key => $tmp_name) {

                           if (!empty($tmp_name)) {
                              $_SESSION['plugin_metademands']['files'][$_POST['form_metademands_id']][$key]['base64'] = base64_encode(file_get_contents($tmp_name));
                              $_SESSION['plugin_metademands']['files'][$_POST['form_metademands_id']][$key]['name'] = $_FILES['filename']['name'][$key];
                           }
                        }
                     }
                     unset($_FILES['filename']);
                  }
                  //               unset($_SESSION['plugin_metademands']);
               }

               if ($KO) {
                  $step = $_POST['step'];
               } else if (isset($_POST['add_metademands'])) {
                  $step = 'add_metademands';
               }
            }
         }
      }
   }
//   Html::back();
   //   if (Session::getCurrentInterface() == 'central') {
   //      Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
   //   } else {
   //      Html::helpHeader(__('Create a demand', 'metademands'));
   //   }
   //
   //   $wizard->showWizard($step, $_POST['form_metademands_id']);
   //
   //   if (Session::getCurrentInterface() == 'central') {
   //      Html::footer();
   //   } else {
   //      Html::helpFooter();
   //   }

   $wizard->showWizard($step, $_POST['metademands_id']);

   if (Session::getCurrentInterface() == 'central') {
      Html::footer();
   } else {
      Html::helpFooter();
   }
} else
   if (isset($_POST['previous'])) {
      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }

      // Resource previous wizard steps
      if ($_POST['step'] == PluginMetademandsMetademand::STEP_SHOW
          && !empty($_POST['resources_id'])
          && !empty($_POST['resources_step'])) {
         switch ($_POST['resources_step']) {
            case 'second_step':
               $resources              = new PluginResourcesResource();
               $values['target']       = Toolbox::getItemTypeFormURL('PluginResourcesWizard');
               $values['withtemplate'] = 0;
               $values['new']          = 0;
               $resources->wizardSecondForm($_POST['resources_id'], $values);
               break;
            case 'third_step':
               $employee = new PluginResourcesEmployee();
               $employee->wizardThirdForm($_POST['resources_id']);
               break;
            case 'four_step':
               $choice = new PluginResourcesChoice();
               $choice->wizardFourForm($_POST['resources_id']);
               break;
            case 'five_step':
               $resource         = new PluginResourcesResource();
               $values['target'] = Toolbox::getItemTypeFormURL('PluginResourcesWizard');
               $resource->wizardFiveForm($_POST['resources_id'], $values);
               break;
         }
         // Else metademand wizard step
      } else {
         switch ($_POST['step']) {
            case 1:
               $_POST['step'] = PluginMetademandsMetademand::STEP_INIT;
               break;
            default:
               $_POST['step'] = $_POST['step'] - 1;
               break;
         }
         $plugin = new Plugin();
         if ($plugin->isActivated('servicecatalog')
             && $_POST['step'] == PluginMetademandsMetademand::STEP_LIST
             && Session::haveRight("plugin_servicecatalog", READ)) {
            Html::redirect($CFG_GLPI["root_doc"] . "/plugins/servicecatalog/front/main.form.php");
         } else if($_POST['step'] == 2){
            if(isset($_SESSION['metademands_hide'])){
               unset($_SESSION['metademands_hide']);
            }
            if(isset($_SESSION['son_meta'])){
               unset($_SESSION['son_meta']);
            }
         }
         $wizard->showWizard($_POST['step'], $_POST['metademands_id']);
      }

      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   } else if (isset($_POST['return'])) {
      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }

      $wizard->showWizard(PluginMetademandsMetademand::STEP_INIT);

      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   } else if (isset($_POST['upload_files'])) {
      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }

      if (!empty($_FILES['filename']['tmp_name'][0])) {
         $wizard->uploadFiles($_POST);
      } else {
         $wizard->showWizard(PluginMetademandsMetademand::STEP_INIT);
      }
      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   } else if (isset($_POST['add_to_basket'])) {

      $KO   = false;
      $step = PluginMetademandsMetademand::STEP_SHOW;

      $content = [];
      $data    = $field->find(['plugin_metademands_metademands_id' => $_POST['form_metademands_id'],
                               'is_basket'                         => 1]);

      foreach ($data as $id => $value) {

         if (isset($_POST['field'][$id])) {

            if (!$wizard->checkMandatoryFields($value, ['id'    => $id,
                                                        'value' => $_POST['field'][$id]],
                                               $_POST['field'])) {
               //            foreach ($_POST['field'] as $key => $field) {
               //               if (is_array($field)) {
               //
               //               } else {
               //                  $field = str_replace('\r\n', '&#x0A;', $field);
               //                  $_POST['field'][$key] = $field;
               //               }
               //            }
               $KO = true;
            }
            $content[$id]['plugin_metademands_fields_id'] = $id;
            if (isset($_POST['_filename']) && $value['type'] == "upload") {
               $content[$id]['value'] = PluginMetademandsField::_serialize($_POST['_filename']);
            } else {
               $content[$id]['value'] = (is_array($_POST['field'][$id])) ? PluginMetademandsField::_serialize($_POST['field'][$id]) : $_POST['field'][$id];
            }
            $content[$id]['value2'] = (isset($_POST['field'][$id . "-2"])) ? $_POST['field'][$id . "-2"] : "";
            $content[$id]['item']   = $value['item'];
            $content[$id]['type']   = $value['type'];
         } else {
            $content[$id]['plugin_metademands_fields_id'] = $id;
            if (isset($_POST['_filename']) && $value['type'] == "upload") {
                  $files = [];
                  foreach($_POST['_filename'] as $key => $filename){
                     $files[$key]['_prefix_filename'] = $_POST['_prefix_filename'][$key];
                     $files[$key]['_tag_filename'] = $_POST['_tag_filename'][$key];
                     $files[$key]['_filename'] = $_POST['_filename'][$key];
                  }
               $content[$id]['value'] = json_encode($files);
            } else {
               $content[$id]['value'] = '';
            }
            $content[$id]['value2'] = '';
            $content[$id]['item']   = $value['item'];
            $content[$id]['type']   = $value['type'];
         }
      }

      if ($KO === false) {

         $basketline = new PluginMetademandsBasketline();
         $basketline->addToBasket($content, $_POST['form_metademands_id']);
      }
      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }

      $wizard->showWizard($step, $_POST['metademands_id']);

      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   } else if (isset($_POST['deletebasketline'])) {

      $basketline = new PluginMetademandsBasketline();
      $basketline->deleteFromBasket($_POST);

      $step = PluginMetademandsMetademand::STEP_SHOW;

      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");
      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }

      $wizard->showWizard($step, $_POST['metademands_id']);

      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   } else {
      if (Session::getCurrentInterface() == 'central') {
         Html::header(__('Create a demand', 'metademands'), '', "helpdesk", "pluginmetademandsmetademand");

      } else {
         Html::helpHeader(__('Create a demand', 'metademands'));
      }
      if(isset($_SESSION['metademands_hide'])){
         unset($_SESSION['metademands_hide']);
      }
      $wizard->showWizard($_GET['step'], $_GET['metademands_id'], false, $_GET['tickets_id'], $_GET['resources_id'], $_GET['resources_step']);

      if (Session::getCurrentInterface() == 'central') {
         Html::footer();
      } else {
         Html::helpFooter();
      }

   }