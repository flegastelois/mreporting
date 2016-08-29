<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginMreportingHelpdeskplus Extends PluginMreportingBaseclass {

   protected $sql_group_assign  = "1=1",
             $sql_group_request = "1=1",
             $sql_user_assign   = "1=1",
             $sql_type          = "glpi_tickets.type IN (".Ticket::INCIDENT_TYPE.", ".Ticket::DEMAND_TYPE.")",
             $sql_itilcat       = "1=1",

             $sql_join_cat      = "LEFT JOIN glpi_itilcategories cat
                                       ON glpi_tickets.itilcategories_id = cat.id",
             $sql_join_g        = "LEFT JOIN glpi_groups g
                                       ON gt.groups_id = g.id",
             $sql_join_u        = "LEFT JOIN glpi_users u
                                       ON tu.users_id = u.id",
             $sql_join_tt       = "LEFT JOIN glpi_tickettasks tt
                                       ON tt.tickets_id  = glpi_tickets.id",
             $sql_join_tu       = "LEFT JOIN glpi_tickets_users tu
                                       ON tu.tickets_id = glpi_tickets.id
                                       AND tu.type = ".Ticket_User::ASSIGN,
             $sql_join_gt       = "LEFT JOIN glpi_groups_tickets gt
                                       ON gt.tickets_id  = glpi_tickets.id
                                       AND gt.type = ".Group_Ticket::ASSIGN,
             $sql_join_gtr      = "LEFT JOIN glpi_groups_tickets gtr
                                       ON gtr.tickets_id = glpi_tickets.id
                                       AND gtr.type = ".Group_Ticket::REQUESTER;


   function __construct($config = array()) {
      global $LANG;

      parent::__construct($config);

      $this->lcl_slaok = $LANG['plugin_mreporting']['Helpdeskplus']['slaobserved'];
      $this->lcl_slako = $LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved'];

      $mr_values = $_SESSION['mreporting_values'];

      if (isset($mr_values['groups_assign_id'])) {
         if (is_array($mr_values['groups_assign_id'])) {
            $this->sql_group_assign = "gt.groups_id IN (".
                                       implode(',', $mr_values['groups_assign_id']).")";
         } elseif ($mr_values['groups_assign_id'] > 0) {
            $this->sql_group_assign = "gt.groups_id = ".$mr_values['groups_assign_id'];
         }
      }

      if (isset($mr_values['groups_request_id'])) {
         if (is_array($mr_values['groups_request_id'])) {
            $this->sql_group_request = "gtr.groups_id IN (".
                                          implode(',', $mr_values['groups_request_id']).")";
         } elseif ($mr_values['groups_request_id'] > 0) {
            $this->sql_group_request = "gt.groups_id = ".$mr_values['groups_request_id'];
         }
      }

      if (isset($mr_values['users_assign_id'])
          && $mr_values['users_assign_id'] > 0) {
         $this->sql_user_assign = "tu.users_id = ".$mr_values['users_assign_id'];
      }

      if (isset($mr_values['type'])
          && $mr_values['type'] > 0) {
         $this->sql_type = "glpi_tickets.type = ".$mr_values['type'];
      }

      if (isset($mr_values['itilcategories_id'])
          && $mr_values['itilcategories_id'] > 0) {
         $this->sql_itilcat = "glpi_tickets.itilcategories_id = ".$mr_values['itilcategories_id'];
      }
   }

   function reportGlineBacklogs($config = array()) {
      global $DB, $LANG;

      $_SESSION['mreporting_selector']['reportGlineBacklogs'] =
         array('dateinterval', 'period', 'backlogstates', 'multiplegrouprequest',
               'userassign', 'category', 'multiplegroupassign');

      $tab              = array();
      $datas            = array();
      $randname         = $config['randname'];
      $search_new       = PluginMreportingDashboard::getDefaultConfig('show_new');
      $search_solved    = PluginMreportingDashboard::getDefaultConfig('show_solved');
      $search_backlogs  = PluginMreportingDashboard::getDefaultConfig('show_backlog');
      $search_closed    = PluginMreportingDashboard::getDefaultConfig('show_closed');
      $date1            = PluginMreportingDashboard::getDefaultConfig("date1$randname");
      $date2            = PluginMreportingDashboard::getDefaultConfig("date2$randname");

      // If in dashboard mode, overwrite default config with widget config
      if (PluginMreportingDashboard::checkWidgetConfig($config)) {
         $randname        = preg_replace('/[0-9]*/', null, $randname);
         $widget_id       = $config['widget_id'];
         $search_new      = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_new');
         $search_solved   = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_solved');
         $search_backlogs = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_backlog');
         $search_closed   = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_closed');
         $date1           = PluginMreportingDashboard::getWidgetConfig($widget_id,"date1$randname");
         $date2           = PluginMreportingDashboard::getWidgetConfig($widget_id,"date2$randname");
      }

      if($search_new) {
         $sql_create = "SELECT
                  DISTINCT DATE_FORMAT(date, '{$this->period_sort}') as period,
                  DATE_FORMAT(date, '{$this->period_label}') as period_name,
                  COUNT(DISTINCT glpi_tickets.id) as nb
               FROM glpi_tickets
               {$this->sql_join_tu}
               {$this->sql_join_gt}
               {$this->sql_join_gtr}
               WHERE {$this->sql_date_create}
                  AND glpi_tickets.entities_id IN ({$this->where_entities})
                  AND glpi_tickets.is_deleted = '0'
                  AND {$this->sql_type}
                  AND {$this->sql_group_assign}
                  AND {$this->sql_group_request}
                  AND {$this->sql_user_assign}
                  AND {$this->sql_itilcat}
               GROUP BY period
               ORDER BY period";
         foreach ($DB->request($sql_create) as $data) {
            $tab[$data['period']]['open'] = $data['nb'];
            $tab[$data['period']]['period_name'] = $data['period_name'];
         }
      }

      if($search_solved) {
         $sql_solved = "SELECT
                  DISTINCT DATE_FORMAT(solvedate, '{$this->period_sort}') as period,
                  DATE_FORMAT(solvedate, '{$this->period_label}') as period_name,
                  COUNT(DISTINCT glpi_tickets.id) as nb
               FROM glpi_tickets
               {$this->sql_join_tu}
               {$this->sql_join_gt}
               {$this->sql_join_gtr}
               WHERE {$this->sql_date_solve}
                  AND glpi_tickets.entities_id IN ({$this->where_entities})
                  AND glpi_tickets.is_deleted = '0'
                  AND {$this->sql_type}
                  AND {$this->sql_group_assign}
                  AND {$this->sql_group_request}
                  AND {$this->sql_user_assign}
                  AND {$this->sql_itilcat}
               GROUP BY period
               ORDER BY period";
         foreach ($DB->request($sql_solved) as $data) {
            $tab[$data['period']]['solved'] = $data['nb'];
            $tab[$data['period']]['period_name'] = $data['period_name'];
         }
      }

      /**
       * Backlog : Tickets Ouverts à la date en cours...
       */
      if($search_backlogs) {
         $date_array1=explode("-",$date1);
         $time1=mktime(0,0,0,$date_array1[1],$date_array1[2],$date_array1[0]);

         $date_array2=explode("-",$date2);
         $time2=mktime(0,0,0,$date_array2[1],$date_array2[2],$date_array2[0]);

         //if data inverted, reverse it
         if ($time1 > $time2) {
            list($time1, $time2) = array($time2, $time1);
            list($date1, $date2) = array($date2, $date1);
         }

         $begin=strftime($this->period_sort_php ,$time1);
         $end=strftime($this->period_sort_php, $time2);
         $sql_date_backlog =  "DATE_FORMAT(list_date.period_l, '{$this->period_sort}') >= '$begin'
                               AND DATE_FORMAT(list_date.period_l, '{$this->period_sort}') <= '$end'";
         $sql_list_date2 = str_replace('date', 'solvedate', $this->sql_list_date);
         $sql_backlog = "SELECT
            DISTINCT(DATE_FORMAT(list_date.period_l, '$this->period_sort')) as period,
            DATE_FORMAT(list_date.period_l, '$this->period_label') as period_name,
            COUNT(DISTINCT(glpi_tickets.id)) as nb
         FROM (
            SELECT DISTINCT period_l
            FROM (
               SELECT
                  {$this->sql_list_date}
               FROM glpi_tickets
               UNION
               SELECT
                  $sql_list_date2
               FROM glpi_tickets
            ) as list_date_union
         ) as list_date
         LEFT JOIN glpi_tickets
            ON glpi_tickets.date <= list_date.period_l
            AND (glpi_tickets.solvedate > list_date.period_l OR glpi_tickets.solvedate IS NULL)
         {$this->sql_join_tu}
         {$this->sql_join_gt}
         {$this->sql_join_gtr}
         WHERE glpi_tickets.entities_id IN ({$this->where_entities})
               AND glpi_tickets.is_deleted = '0'
               AND {$this->sql_type}
               AND {$this->sql_group_assign}
               AND {$this->sql_group_request}
               AND {$this->sql_user_assign}
               AND {$this->sql_itilcat}
               AND $sql_date_backlog
         GROUP BY period";
         foreach ($DB->request($sql_backlog) as $data) {
            $tab[$data['period']]['backlog'] = $data['nb'];
            $tab[$data['period']]['period_name'] = $data['period_name'];
         }

      }

      if($search_closed) {
         $sql_closed = "SELECT
                  DISTINCT DATE_FORMAT(closedate, '{$this->period_sort}') as period,
                  DATE_FORMAT(closedate, '{$this->period_label}') as period_name,
                  COUNT(DISTINCT glpi_tickets.id) as nb
               FROM glpi_tickets
               {$this->sql_join_tu}
               {$this->sql_join_gt}
               {$this->sql_join_gtr}
               WHERE {$this->sql_date_closed}
                  AND glpi_tickets.entities_id IN ({$this->where_entities})
                  AND glpi_tickets.is_deleted = '0'
                  AND {$this->sql_type}
                  AND {$this->sql_group_assign}
                  AND {$this->sql_group_request}
                  AND {$this->sql_user_assign}
                  AND {$this->sql_itilcat}
               GROUP BY period
               ORDER BY period";
         foreach ($DB->request($sql_closed) as $data) {
            $tab[$data['period']]['closed'] = $data['nb'];
            $tab[$data['period']]['period_name'] = $data['period_name'];
         }
      }

      ksort($tab);

      foreach($tab as $period => $data) {
         if($search_new) $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['opened']][] = (isset($data['open'])) ? $data['open'] : 0;
         if($search_solved) $datas['datas'][_x('status', 'Solved')][] = (isset($data['solved'])) ? $data['solved'] : 0;
         if($search_closed) $datas['datas'][_x('status', 'Closed')][] = (isset($data['closed'])) ? $data['closed'] : 0;
         if($search_backlogs) $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['backlogs']][] = (isset($data['backlog'])) ? $data['backlog'] : 0;
         $datas['labels2'][] = $data['period_name'];
      }

      return $datas;
   }



   function reportVstackbarLifetime($config = array()) {
      global $DB;

      $tab = $datas = $labels2 = array();
      $_SESSION['mreporting_selector']['reportVstackbarLifetime']
         = array('dateinterval', 'period', 'allstates', 'multiplegrouprequest',
                 'multiplegroupassign', 'userassign', 'category');


      if (!isset($_SESSION['mreporting_values']['date2'.$config['randname']]))
         $_SESSION['mreporting_values']['date2'.$config['randname']] = strftime("%Y-%m-%d");


      foreach ($this->status as $current_status) {

         $widget_id     = null;
         $show_status   = PluginMreportingDashboard::getDefaultConfig("status_$current_status");

         // If in dashboard mode, overwrite default config with widget config
         if (PluginMreportingDashboard::checkWidgetConfig($config)) {
            $widget_id     = $config['widget_id'];
            $show_status   = PluginMreportingDashboard::getWidgetConfig($widget_id,
                                                                        "status_$current_status");
         }

         if ($show_status) {
            $status_name = Ticket::getStatus($current_status);
            $sql_status = "SELECT
                     DISTINCT DATE_FORMAT(date, '{$this->period_sort}') as period,
                     DATE_FORMAT(date, '{$this->period_label}') as period_name,
                     COUNT(DISTINCT glpi_tickets.id) as nb
                  FROM glpi_tickets
                  {$this->sql_join_tu}
                  {$this->sql_join_gt}
                  {$this->sql_join_gtr}
                  WHERE {$this->sql_date_create}
                     AND glpi_tickets.entities_id IN ({$this->where_entities})
                     AND glpi_tickets.is_deleted = '0'
                     AND glpi_tickets.status = $current_status
                     AND {$this->sql_type}
                     AND {$this->sql_itilcat}
                     AND {$this->sql_group_assign}
                     AND {$this->sql_group_request}
                     AND {$this->sql_user_assign}
                  GROUP BY period
                  ORDER BY period";
            $res = $DB->query($sql_status);
            while ($data = $DB->fetch_assoc($res)) {
               $tab[$data['period']][$status_name] = $data['nb'];
               $labels2[$data['period']] = $data['period_name'];
            }
         }
      }

      //ascending order of datas by date
      ksort($tab);

      //fill missing datas with zeros
      $datas = $this->fillStatusMissingValues($tab, $labels2, $widget_id);

      return $datas;
   }



   function reportVstackbarTicketsgroups($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportVstackbarTicketsgroups'] =
         array('dateinterval', 'allstates', 'multiplegroupassign', 'category');

      $tab = array();
      $datas = array();

      if (!isset($_SESSION['mreporting_values']['date2'.$config['randname']])) {
         $_SESSION['mreporting_values']['date2'.$config['randname']] = strftime("%Y-%m-%d");
      }

      foreach ($this->status as $current_status) {

         $widget_id = null;
         $show_status = PluginMreportingDashboard::getDefaultConfig("status_$current_status");

         // If in dashboard mode, overwrite default config with widget config
         if (PluginMreportingDashboard::checkWidgetConfig($config)) {
            $widget_id     = $config['widget_id'];
            $show_status   = PluginMreportingDashboard::getWidgetConfig($widget_id,
                                                                        "status_$current_status");
         }

         if ($show_status) {
            $status_name = Ticket::getStatus($current_status);
            $sql_status = "SELECT
                     DISTINCT g.completename AS group_name,
                     COUNT(DISTINCT glpi_tickets.id) AS nb
                  FROM glpi_tickets
                  {$this->sql_join_gt}
                  {$this->sql_join_g}
                  WHERE {$this->sql_date_create}
                     AND glpi_tickets.entities_id IN ({$this->where_entities})
                     AND glpi_tickets.is_deleted = '0'
                     AND glpi_tickets.status = $current_status
                     AND {$this->sql_type}
                     AND {$this->sql_itilcat}
                     AND {$this->sql_group_assign}
                  GROUP BY group_name
                  ORDER BY group_name";
            $res = $DB->query($sql_status);
            while ($data = $DB->fetch_assoc($res)) {
               if (empty($data['group_name'])) $data['group_name'] = __("None");
               $tab[$data['group_name']][$status_name] = $data['nb'];
            }
         }
      }

      //ascending order of datas by date
      ksort($tab);

      //fill missing datas with zeros
      $datas = $this->fillStatusMissingValues($tab, array(), $widget_id);

      return $datas;
   }



   function reportVstackbarTicketstech($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportVstackbarTicketstech']
         = array('dateinterval', 'multiplegroupassign', 'allstates', 'category');

      $tab = array();
      $datas = array();

      if (!isset($_SESSION['mreporting_values']['date2'.$config['randname']]))
         $_SESSION['mreporting_values']['date2'.$config['randname']] = strftime("%Y-%m-%d");

      foreach ($this->status as $current_status) {

         $widget_id     = null;
         $show_status   = PluginMreportingDashboard::getDefaultConfig("status_$current_status");

         // If in dashboard mode, overwrite default config with widget config
         if (PluginMreportingDashboard::checkWidgetConfig($config)) {
            $widget_id     = $config['widget_id'];
            $show_status   = PluginMreportingDashboard::getWidgetConfig($widget_id,
                                                                        "status_$current_status");
         }

         if ($show_status) {
            $status_name = Ticket::getStatus($current_status);

            $sql_create = "SELECT
                     DISTINCT CONCAT(u.firstname, ' ', u.realname) AS completename,
                     u.name as name,
                     u.id as u_id,
                     COUNT(DISTINCT glpi_tickets.id) AS nb
                  FROM glpi_tickets
                  {$this->sql_join_tu}
                  {$this->sql_join_gt}
                  {$this->sql_join_gtr}
                  {$this->sql_join_u}
                  WHERE {$this->sql_date_create}
                     AND glpi_tickets.entities_id IN ({$this->where_entities})
                     AND glpi_tickets.is_deleted = '0'
                     AND glpi_tickets.status = $current_status
                     AND {$this->sql_group_assign}
                     AND {$this->sql_group_request}
                     AND {$this->sql_type}
                     AND {$this->sql_itilcat}
                  GROUP BY name
                  ORDER BY name";
            $res = $DB->query($sql_create);
            while ($data = $DB->fetch_assoc($res)) {
               $data['name'] = empty($data['completename']) ? __("None") : $data['completename'];

               if (!isset($tab[$data['name']][$status_name])) {
                  $tab[$data['name']][$status_name] = 0;
               }

               $tab[$data['name']][$status_name]+= $data['nb'];
            }
         }
      }

      //ascending order of datas by date
      ksort($tab);

      //fill missing datas with zeros
      $datas = $this->fillStatusMissingValues($tab, array(), $widget_id);

      return $datas;
   }

   function reportHbarTopcategory($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportHbarTopcategory']
         = array('dateinterval', 'limit', 'userassign', 'multiplegrouprequest', 'multiplegroupassign', 'type');

      $tab = array();
      $datas = array();

      $sql_create = "SELECT DISTINCT glpi_tickets.itilcategories_id,
                  COUNT(DISTINCT glpi_tickets.id) as nb,
                  cat.completename
               FROM glpi_tickets
               {$this->sql_join_cat}
               {$this->sql_join_tu}
               {$this->sql_join_gt}
               {$this->sql_join_gtr}
               WHERE {$this->sql_date_create}
                  AND glpi_tickets.entities_id IN ({$this->where_entities})
                  AND glpi_tickets.is_deleted = '0'
                  AND {$this->sql_type}
                  AND {$this->sql_group_assign}
                  AND {$this->sql_group_request}
                  AND {$this->sql_user_assign}
               GROUP BY cat.completename
               ORDER BY nb DESC
               LIMIT 0, ";
      $sql_create .= (isset($_SESSION['mreporting_values']['glpilist_limit']))
                     ? $_SESSION['mreporting_values']['glpilist_limit'] : 20;

      $res = $DB->query($sql_create);
      while ($data = $DB->fetch_assoc($res)) {
         if (empty($data['completename'])) $data['completename'] = __("None");
         $datas['datas'][$data['completename']] = $data['nb'];
      }

      return $datas;
   }

   function reportHbarTopapplicant($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportHbarTopapplicant'] = array('dateinterval', 'limit', 'type');

      $tab = array();
      $datas = array();

      $sql_create = "SELECT DISTINCT gt.groups_id,
                  COUNT(DISTINCT glpi_tickets.id) AS nb,
                  g.completename
               FROM glpi_tickets
               {$this->sql_join_gt}
               {$this->sql_join_g}
               WHERE {$this->sql_date_create}
                  AND {$this->sql_type}
                  AND glpi_tickets.entities_id IN ({$this->where_entities})
                  AND glpi_tickets.is_deleted = '0'
               GROUP BY g.completename
               ORDER BY nb DESC
               LIMIT 0, ";
      $sql_create .= (isset($_SESSION['mreporting_values']['glpilist_limit']))
                     ? $_SESSION['mreporting_values']['glpilist_limit'] : 20;

      $res = $DB->query($sql_create);
      while ($data = $DB->fetch_assoc($res)) {
         if (empty($data['completename'])) $data['completename'] = __("None");
         $datas['datas'][$data['completename']] = $data['nb'];
      }

      return $datas;
   }

   function reportVstackbarGroupChange($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportVstackbarGroupChange']
         = array('dateinterval', 'userassign', 'category',
                 'multiplegrouprequest', 'multiplegroupassign');

      $datas = array();

      $query = "SELECT COUNT(DISTINCT ticc.id) as nb_ticket,
            ticc.nb_add_group - 1 as nb_add_group
         FROM (
            SELECT
               glpi_tickets.id,
               COUNT(glpi_tickets.id) as nb_add_group
            FROM glpi_tickets
            LEFT JOIN glpi_logs logs_tic
               ON  logs_tic.itemtype = 'Ticket'
               AND logs_tic.items_id = glpi_tickets.id
               AND logs_tic.itemtype_link = 'Group'
               AND logs_tic.linked_action = 15 /* add action */
            {$this->sql_join_cat}
            {$this->sql_join_tu}
            {$this->sql_join_gt}
            {$this->sql_join_gtr}
            WHERE {$this->sql_date_create}
               AND glpi_tickets.entities_id IN ({$this->where_entities})
               AND glpi_tickets.is_deleted = '0'
               AND {$this->sql_type}
               AND {$this->sql_group_assign}
               AND {$this->sql_group_request}
               AND {$this->sql_user_assign}
               AND {$this->sql_itilcat}
            GROUP BY glpi_tickets.id
            HAVING nb_add_group > 0
         ) as ticc
         GROUP BY nb_add_group";

      $result = $DB->query($query);

      $datas['datas'] = array();
      while ($ticket = $DB->fetch_assoc($result)) {
         $datas['labels2'][$ticket['nb_add_group']] = $ticket['nb_add_group'];
         $datas['datas'][__("Number of tickets")][$ticket['nb_add_group']] = $ticket['nb_ticket'];
      }

      return $datas;
   }


   function reportLineActiontimeVsSolvedelay($config = array()) {
      global $DB;

      $_SESSION['mreporting_selector']['reportLineActiontimeVsSolvedelay'] =
         array('dateinterval', 'period', 'multiplegrouprequest',
               'userassign', 'category', 'multiplegroupassign');

      $query = "SELECT
         DATE_FORMAT(glpi_tickets.date, '{$this->period_sort}')  as period,
         DATE_FORMAT(glpi_tickets.date, '{$this->period_label}') as period_name,
         ROUND(AVG(actiontime_vs_solvedelay.time_percent), 1) as time_percent
       FROM glpi_tickets
         LEFT JOIN (
            SELECT
               glpi_tickets.id AS tickets_id,
               (SUM(tt.actiontime) * 100) / glpi_tickets.solve_delay_stat as time_percent
            FROM glpi_tickets
            {$this->sql_join_tt}
            {$this->sql_join_tu}
            {$this->sql_join_gt}
            {$this->sql_join_gtr}
            WHERE glpi_tickets.solve_delay_stat > 0
               AND tt.actiontime IS NOT NULL
               AND glpi_tickets.entities_id IN ({$this->where_entities})
               AND glpi_tickets.is_deleted = '0'
               AND {$this->sql_date_create}
               AND {$this->sql_type}
               AND {$this->sql_group_assign}
               AND {$this->sql_group_request}
               AND {$this->sql_user_assign}
               AND {$this->sql_itilcat}
            GROUP BY glpi_tickets.id
         ) AS actiontime_vs_solvedelay
            ON actiontime_vs_solvedelay.tickets_id = glpi_tickets.id
         WHERE {$this->sql_date_create}
         GROUP BY period
         ORDER BY period";
      $data = array();
      foreach ($DB->request($query) as $result) {
         $data['datas'][$result['period_name']] = floatval($result['time_percent']);
         $data['labels2'][$result['period_name']] = $result['period_name'];
      }

      return $data;
   }



   function reportGlineNbTicketBySla($config = array()) { Html::printCleanArray($config);
      global $DB;

      $area = false;
      $datas = array();

      $_SESSION['mreporting_selector']['reportGlineNbTicketBySla']
         = array('dateinterval', 'period', 'allSlasWithTicket');

      $slas = PluginMreportingDashboard::getDefaultConfig('slas');

      // If in dashboard mode, overwrite default config with widget config
      if (isset($config['widget_id']) &&
          PluginMreportingDashboard::checkWidgetConfig($config['widget_id'])) {
         $slas = $_SESSION['mreporting_values_dashboard'][$config['widget_id']]['slas'];
      } echo "slas :: $slas";

      if (isset($slas) && !empty($slas)) {
         //get dates used in this period
         $query_date = "SELECT DISTINCT DATE_FORMAT(`date`, '{$this->period_sort}') AS period,
            DATE_FORMAT(`date`, '{$this->period_label}') AS period_name
         FROM `glpi_tickets`
         INNER JOIN glpi_slas sla ON sla.id = `glpi_tickets`.slts_tto_id
                                  OR sla.id = `glpi_tickets`.slts_ttr_id
         WHERE {$this->sql_date_create}
            AND status IN (" . implode(
                  ',',
                  array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray())
               ) . ")
            AND glpi_tickets.`entities_id` IN (" . $this->where_entities . ")
            AND glpi_tickets.`is_deleted` = '0'
            AND sla.id IN (".implode(',', $slas).")
         ORDER BY `date` ASC"; echo $query_date;
         $res_date = $DB->query($query_date);

         $dates = array();
         while ($data = $DB->fetch_assoc($res_date)) {
            $dates[$data['period']] = $data['period'];
         }

         $tmp_date = array();
         foreach (array_values($dates) as $id) {
            $tmp_date[] = $id;
         }

         $query = "SELECT DISTINCT
            DATE_FORMAT(`date`, '{$this->period_sort}') AS period,
            DATE_FORMAT(`date`, '{$this->period_label}') AS period_name,
            count(glpi_tickets.id) AS nb,
            s.name,
            CASE WHEN solve_delay_stat <= s.resolution_time THEN 'ok' ELSE 'nok' END AS respected_sla
         FROM `glpi_tickets`
         INNER JOIN `glpi_slas` s
            ON slas_id = s.id
         WHERE {$this->sql_date_create}
         AND status IN (" . implode(
               ',',
               array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray())
            ) . ")
         AND glpi_tickets.entities_id IN (" . $this->where_entities . ")
         AND glpi_tickets.is_deleted = '0'";
         if (isset($slas)) {
            $query .= " AND s.id IN (".implode(',', $slas).") ";
         }
         $query .= "GROUP BY s.name, period, respected_sla";

         $result = $DB->query($query);
         while ($data = $DB->fetch_assoc($result)) {
            $datas['labels2'][$data['period']] = $data['period_name'];
            if ($data['respected_sla'] == 'ok') {
               $value = $this->lcl_slaok;
            } else {
               $value = $this->lcl_slako;
            }
            $datas['datas'][$data['name'] . ' ' . $value][$data['period']] = $data['nb'];
         }

         if (isset($datas['datas'])) {
            foreach ($datas['datas'] as &$data) {
               $data = $data + array_fill_keys($tmp_date, 0);
            }
         }
      }

      return $datas;
   }


   public function reportHgbarRespectedSlasByTopCategory($config = array()) {
      global $DB;

      $area = false;

      $_SESSION['mreporting_selector']['reportHgbarRespectedSlasByTopCategory']
         = array('dateinterval', 'limit', 'categories');

      $datas = array();
      $categories = array();

      if (isset($_POST['categories']) && $_POST['categories'] > 0) {
         $category = $_POST['categories'];
      } else {
         $category = false;
      }

      $category_limit = isset($_POST['glpilist_limit']) ? $_POST['glpilist_limit'] : 10;

      $_SESSION['glpilist_limit'] = $category_limit;

      if (!$category) {
         $query_categories = "SELECT
            count(glpi_tickets.id) as nb,
            c.id
         FROM glpi_tickets
         INNER JOIN glpi_slas s
            ON glpi_tickets.slas_id = s.id
         INNER JOIN glpi_itilcategories c
            ON glpi_tickets.itilcategories_id = c.id
         WHERE " . $this->sql_date_create . "
         AND glpi_tickets.entities_id IN (" . $this->where_entities . ")
         AND glpi_tickets.is_deleted = '0'
         GROUP BY c.id
         ORDER BY nb DESC
         LIMIT " . $category_limit;

         $result_categories = $DB->query($query_categories);
         while ($data = $DB->fetch_assoc($result_categories)) {
            $categories[] = $data['id'];
         }
      }

      $query = "SELECT COUNT(glpi_tickets.id) as nb,
            CASE WHEN glpi_tickets.solve_delay_stat <= s.resolution_time
               THEN 'ok'
               ELSE 'nok'
            END AS respected_sla,
            c.id,
            c.name
         FROM glpi_tickets
         INNER JOIN glpi_slas s
            ON glpi_tickets.slas_id = s.id
         INNER JOIN glpi_itilcategories c
            ON glpi_tickets.itilcategories_id = c.id
         WHERE " . $this->sql_date_create . "
         AND glpi_tickets.entities_id IN (" . $this->where_entities . ")
         AND glpi_tickets.is_deleted = '0'";
         if ($category) {
            $query .= " AND c.id = " . $category;
         } elseif (!empty($categories)) {
            $query .= " AND c.id IN (" . implode(',', $categories) . ")";
         }
         $query .= " GROUP BY respected_sla, c.id
         ORDER BY nb DESC";

      $result = $DB->query($query);
      while ($data = $DB->fetch_assoc($result)) {
         $value = ($data['respected_sla'] == 'ok') ? $this->lcl_slaok
                                                   : $this->lcl_slako;
         $datas['datas'][$data['name']][$value] = $data['nb'];
      }
      $datas['labels2'] = array($this->lcl_slaok => $this->lcl_slaok,
                                $this->lcl_slako => $this->lcl_slako);

      if (isset($datas['datas'])) {
         foreach ($datas['datas'] as &$data) {
            $data = $data + array_fill_keys($datas['labels2'], 0);
         }
      }

      return $datas;
   }

   public function reportHgbarRespectedSlasByTechnician($config = array()) {
      global $DB;

      $area = false;
      $datas = array();

      $_SESSION['mreporting_selector']['reportHgbarRespectedSlasByTechnician'] = array('dateinterval');

      $query = "SELECT
            CONCAT(u.firstname, ' ', u.realname) as fullname,
            u.id,
            COUNT(glpi_tickets.id) as nb,
            CASE WHEN glpi_tickets.solve_delay_stat <= s.resolution_time
               THEN 'ok'
               ELSE 'nok'
            END AS respected_sla
         FROM glpi_tickets
         INNER JOIN glpi_slas s
            ON glpi_tickets.slas_id = s.id
         INNER JOIN glpi_tickets_users tu
            ON tu.tickets_id = glpi_tickets.id
            AND tu.type = " . Ticket_User::ASSIGN . "
         INNER JOIN glpi_users u
            ON u.id = tu.users_id
         WHERE " . $this->sql_date_create . "
         AND glpi_tickets.entities_id IN ({$this->where_entities})
         AND glpi_tickets.is_deleted = '0'
         GROUP BY respected_sla, u.id
         ORDER BY nb DESC";

      $result = $DB->query($query);
      while ($data = $DB->fetch_assoc($result)) {
         if ($data['respected_sla'] == 'ok') {
            $value = $this->lcl_slaok;
         } else {
            $value = $this->lcl_slako;
         }
         $datas['datas'][$data['fullname']][$value] = $data['nb'];
      }
      $datas['labels2'] = array($this->lcl_slaok => $this->lcl_slaok,
                                $this->lcl_slako => $this->lcl_slako);

      if (isset($datas['datas'])) {
         foreach ($datas['datas'] as &$data) {
            $data = $data + array_fill_keys($datas['labels2'], 0);
         }
      }

      return $datas;
   }

   function fillStatusMissingValues($tab, $labels2 = array(), $widget_id) {
      $datas = array();
      foreach($tab as $name => $data) {
         foreach ($this->status as $current_status) {

            $show_status = PluginMreportingDashboard::getDefaultConfig("status_$current_status");

            // If in dashboard mode, overwrite default config with widget config
            if (PluginMreportingDashboard::checkWidgetConfig($widget_id)) {
               $show_status = PluginMreportingDashboard::checkWidgetConfig($widget_id,
                                                                           "status$current_status");
            }

            if ($show_status) {
               $status_name = Ticket::getStatus($current_status);
               if (isset($data[$status_name])) {
                  $datas['datas'][$status_name][] = $data[$status_name];
               } else {
                  $datas['datas'][$status_name][] = 0;
               }
            }
         }
         if (empty($labels2)) {
            $datas['labels2'][] = $name;
         } else {
            $datas['labels2'][] = $labels2[$name];
         }
      }
      return $datas;
   }

   static function selectorBacklogstates() {
      global $LANG;

      echo "<br /><b>".$LANG['plugin_mreporting']['Helpdeskplus']['backlogstatus']." : </b><br />";

      $show_new     = (PluginMreportingDashboard::getDefaultConfig('show_new'))
                    ? 'checked="checked"' : '';
      $show_solved  = (PluginMreportingDashboard::getDefaultConfig('show_solved'))
                    ? 'checked="checked"' : '';
      $show_backlog = (PluginMreportingDashboard::getDefaultConfig('show_backlog'))
                    ? 'checked="checked"' : '';
      $show_closed  = (PluginMreportingDashboard::getDefaultConfig('show_closed'))
                    ? 'checked="checked"' : '';

      // If in dashboard mode, overwrite default config with widget config
      if (PluginMreportingDashboard::checkWidgetConfig($_REQUEST)) {
         $widget_id     = $_REQUEST['widget_id'];
         $show_new      = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_new')
                        ? 'checked="checked"' : '';
         $show_solved   = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_solved')
                        ? 'checked="checked"' : '';
         $show_backlog  = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_backlog')
                        ? 'checked="checked"' : '';
         $show_closed   = PluginMreportingDashboard::getWidgetConfig($widget_id,'show_closed')
                        ? 'checked="checked"' : '';
      }

      // Opened
      echo '<label>';
      echo '<input type="hidden" name="show_new" value="0" /> ';
      echo '<input type="checkbox" name="show_new" value="1"';
      echo " $show_new";
      echo ' /> ';
      echo $LANG['plugin_mreporting']['Helpdeskplus']['opened'];
      echo '</label>';

      // Solved
      echo '<label>';
      echo '<input type="hidden" name="show_solved" value="0" /> ';
      echo '<input type="checkbox" name="show_solved" value="1"';
      echo "$show_solved";
      echo ' /> ';
      echo _x('status', 'Solved');
      echo '</label>';

      echo "<br />";

      // Backlog
      echo '<label>';
      echo '<input type="hidden" name="show_backlog" value="0" /> ';
      echo '<input type="checkbox" name="show_backlog" value="1"';
      echo "$show_backlog";
      echo ' /> ';
      echo $LANG['plugin_mreporting']['Helpdeskplus']['backlogs'];
      echo '</label>';

      // Closed
      echo '<label>';
      echo '<input type="hidden" name="show_closed" value="0" /> ';
      echo '<input type="checkbox" name="show_closed" value="1"';
      echo "$show_closed";
      echo ' /> ';
      echo _x('status', 'Closed');
      echo '</label>';
   }


   function reportVstackbarRespectedSlasByGroup($config = array()) {
      global $DB, $LANG;

      $datas = array();

      $_SESSION['mreporting_selector']['reportVstackbarRespectedSlasByGroup']
         = array('dateinterval', 'allSlasWithTicket');

      $this->sql_date_create = PluginMreportingCommon::getSQLDate("t.date",
                                                                  $config['delay'],
                                                                  $config['randname']);

      $slas = PluginMreportingDashboard::getDefaultConfig('slas');

      if (isset($config['widget_id']) &&
          PluginMreportingDashboard::checkWidgetConfig($config['widget_id'])) {
         $slas = $_SESSION['mreporting_values_dashboard'][$config['widget_id']]['slas'];
      }

      if (isset($slas) && !empty($slas)) {

         $query = "SELECT COUNT(t.id) AS nb,
               gt.groups_id as groups_id,
               s.name,
               CASE WHEN t.solve_delay_stat <= s.resolution_time
               THEN 'ok'
               ELSE 'nok'
               END AS respected_sla
            FROM `glpi_tickets` t
            INNER JOIN `glpi_groups_tickets` gt
               ON gt.tickets_id = t.id
               AND gt.type = ".CommonITILActor::ASSIGN."
            INNER JOIN `glpi_slas` s ON t.slas_id = s.id
            WHERE {$this->sql_date_create}
               AND t.status IN (" . implode(
                           ',',
                           array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray())
                     ) . ")
               AND t.entities_id IN ({$this->where_entities})
               AND t.is_deleted = '0'
               AND s.id IN (".implode(',', $slas).")
            GROUP BY gt.groups_id, respected_sla;";
         $result = $DB->query($query);

         while ($data = $DB->fetch_assoc($result)) {
            $gp = new Group();
            $gp->getFromDB($data['groups_id']);

            $datas['labels2'][$gp->fields['name']] = $gp->fields['name'];

            if ($data['respected_sla'] == 'ok'){
               $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slaobserved']][$gp->fields['name']] = $data['nb'];
            } else {
               $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved']][$gp->fields['name']] = $data['nb'];
            }

         }

         // Ajout des '0' manquants :
         $gp = new Group();
         $gp_found = $gp->find("", "name"); //Tri précose qui n'est pas utile

         foreach($gp_found as $group){
         	$group_name = $group['name'];
           if(!isset($datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slaobserved']][$group_name])){
              $datas['labels2'][$group_name] = $group_name;
              $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slaobserved']][$group_name] = 0;
           }
           if(!isset($datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved']][$group_name])){
              $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved']][$group_name] = 0;
           }
         }

         //Flip array to have observed SLA first
         arsort($datas['datas']);

         //Array alphabetic sort
         //For PNG mode, it is important to sort by date on each item
         ksort($datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slaobserved']]);
         ksort($datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved']]);

         //For SVG mode, labels2 sort is ok
         asort($datas['labels2']);

         $datas['unit'] = '%';
      }

      return $datas;
   }

   function reportVstackbarNbTicketBySla($config = array()) {
      global $DB, $LANG;

      $area = false;

      $_SESSION['mreporting_selector']['reportVstackbarNbTicketBySla'] = array('dateinterval', 'allSlasWithTicket');

      $datas = array();
      $tmp_datas = array();

      $this->sql_date_create = PluginMreportingCommon::getSQLDate("t.date",
                                                                  $config['delay'],
                                                                  $config['randname']);

      $slas = PluginMreportingDashboard::getDefaultConfig('slas');

      if (PluginMreportingDashboard::checkWidgetConfig($config)) {
         $slas = $_SESSION['mreporting_values_dashboard'][$config['widget_id']]['slas'];
      }

      if (isset($slas) && !empty($slas)) {

         $query = "SELECT count(t.id) AS nb, s.name,
                       CASE WHEN t.solve_delay_stat <= s.resolution_time
                        THEN 'ok'
                        ELSE 'nok'
                        END AS respected_sla
                     FROM `glpi_tickets` t
                     INNER JOIN `glpi_slas` s ON t.slas_id = s.id
                     WHERE {$this->sql_date_create}
                     AND t.status IN (" . implode(',',
                              array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray())
                           ) . ")
                     AND t.entities_id IN ({$this->where_entities})
                     AND t.is_deleted = '0'
                     AND s.id IN (".implode(',', $slas).")
                     GROUP BY s.name, respected_sla;";

         $result = $DB->query($query);
         while ($data = $DB->fetch_assoc($result)) {
            $tmp_datas[$data['name']][$data['respected_sla']] = $data['nb'];
         }

         foreach ($tmp_datas as $key => $value) {
            $datas['labels2'][$key] = $key;
            $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slaobserved']][$key]
               = !empty($value['ok']) ? $value['ok'] : 0;
            $datas['datas'][$LANG['plugin_mreporting']['Helpdeskplus']['slanotobserved']][$key]
               = !empty($value['nok']) ? $value['nok'] : 0;
         }
      }

      return $datas;
   }


   private function _getPeriod() {
      if (isset($_REQUEST['period']) && !empty($_REQUEST['period'])) {
         switch ($_REQUEST['period']) {
            case 'day':
               $this->_period_sort = '%y%m%d';
               $this->_period_label = '%d %b %Y';
               break;
            case 'week':
               $this->_period_sort = '%y%u';
               $this->_period_label = 'S-%u %Y';
               break;
            case 'month':
               $this->_period_sort = '%y%m';
               $this->_period_label = '%b %Y';
               break;
            case 'year':
               $this->_period_sort = '%Y';
               $this->_period_label = '%Y';
               break;
            default :
               $this->_period_sort = '%y%m';
               $this->_period_label = '%b %Y';
               break;
         }
      } else {
         $this->_period_sort = '%y%m';
         $this->_period_label = '%b %Y';
      }
   }
}
