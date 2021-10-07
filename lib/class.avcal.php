<?php
class avcal
{

  /**
   * @private string $mode The display mode, values are 'view' or 'edit'
   */
    private $mode = 'view';

    /**
     * @private integer $object_id The id of an object
     */
    private $object_id;

    /**
     * @private string $date The date formatted as 'YYYY-MM-DD'
     */
    private $date;

    /**
     * @private integer $year The year part of date, format 'YYYY'
     */
    private $year;

    /**
     * @private integer $month The month part of date, format 'MM'
     */
    private $month;

    /**
     * @private integer $day The day part of date, format 'DD'
     */
    private $day;

    /**
     * @private array $booked_dates An array of booked dates, dates formatted as 'YYYY-MM-DD'
     */
    private $booked_dates;

    /**
     * @public array $options Options
     *    the possible options are:
     *    integer week_start = The number of the day the week starts with, 1 = monday, ..., 7 = sunday
     *    string base_link = The base href of the current page, index.php?page=xxx&suppage=xxx&object_id=xxx&func=update
     *    boolean mark_today = True if current day should be styled with a special css class, false otherwise
     *    string today_date_class = The css class name for the current day, default is 'today'
     *    boolean mark_passed = True if passed days should be styled with a special css class, false otherwise
     *    string passed_date_class = The css class name for passed days, default is 'passed'
     *    string booked_date_class = The css class name for booked days, default is 'booked'
     *    boolean table_six_rows = True if the month table should always has six week rows, false otherwise
     */
    private $options = array(
              'week_start' => 1,  // monday
              'base_link' => null,
              'mark_today' => true,
              'today_date_class' => 'today',
              'mark_passed' => false,
              'passed_date_class' => 'passed',
              'booked_date_class' => 'booked',
              'table_six_rows' => true
              );

    /**
     * PHP 5 Constructor
     *
     * @access  public
     *
     * @param   integer   $object_id: the object id
     * @param   string    $date: the date
     * @param   integer   $year: the year
     * @param   integer   $month: the month
     */
    public function __construct($object_id = null, $date = null, $year = null, $month = null)
    {
        $this->object_id = (int) $object_id;

        if (is_null($year) || is_null($month)) {
            if (!is_null($date)) {
                //-------- strtotime the submitted date to ensure correct format
                $this->date = date('Y-m-d', strtotime($date));
                $this->year = date('Y', strtotime($date));
                $this->month = date('m', strtotime($date));
                $this->day = date('d', strtotime($date));
            } else {
                //-------------------------- no date submitted, use today's date
                $this->date = date('Y-m-d');
                $this->year = date('Y');
                $this->month = date('m');
                $this->day = date('d');
            }
        } else {
            $this->year = $year;
            $this->month = str_pad($month, 2, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Set the edit mode for display in backend
     *
     * @access  public
     */
    public function setEditMode()
    {
        $this->mode = 'edit';
        $this->options['base_link'] = 'index.php?page=avcal/calendar&amp;object_id='.$this->object_id;
    }

    /**
     * Set the value for a special option
     *
     * @access  public
     *
     * @param   string   $key: the string key of the option
     * @param   string   $value: the value of the option
     * @return  boolean  true if the option exists, false otherwise
     */
    public function setOption($key, $value)
    {
        if (array_key_exists($key, $this->options)) {
            $this->options[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Get the value for a special option
     *
     * @access  public
     *
     * @param   string   $key: the string key of the option
     * @return  string   the value of the option
     */
    public function getOption($key)
    {
        if (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        return '';
    }

    /**
     * Get the numerical weekday of the date
     *
     * @access  private
     *
     * @param   integer   $date: the timestamp of the date
     * @return  integer   the numerical weekday of the date, 1 = monday, 2 = tuesday, ...
     */
    private function getDayOfWeek($date)
    {
        $day_of_week = date('N', $date);
        if (!is_numeric($day_of_week)) {
            $day_of_week = date('w', $date);
            if ($day_of_week == 0) {
                $day_of_week = 7;
            }
        }
        return $day_of_week;
    }

    /**
     * Fill the array "booked_dates" from database
     *
     * @access  private
     *
     * @param   integer   $year: the year
     * @param   integer   $month: the month
     * @return  boolean   true if booked days exists, false otherwise
     */
    private function setBookedDates($year = null, $month = null)
    {
        global $REX;

        //-------------- override class values if year/month are passed directly
        $year = (is_null($year)) ? $this->year : $year;
        $month = (is_null($month)) ? $this->month : $month;
        if ($month > 12) {
            $month = $month - 12;
            $year = $year + 1;
        } elseif ($month < 1) {
            $month = $month + 12;
            $year = $year - 1;
        }

        $sql = rex_sql::factory();
        //$sql->setDebug();
        $sql->setTable(rex::getTablePrefix().'avcal');
        $sql->setWhere("object_id = ". $this->object_id." AND YEAR(booked_day) = ". $year ." AND MONTH(booked_day) = ". $month);
        $sql->select('booked_day, state');
        if ($sql->getRows() > 0) {
            $dates = array();
            for ($i = 0; $i < $sql->getRows(); $i++) {
                $dates[$sql->getValue('booked_day')] = $sql->getValue('state');
                $sql->next();
            }
            $this->booked_dates = $dates;
            return true;
        }
        return false;
    }

    /**
     * Get the html code for a calendar month
     *
     * @access  public
     *
     * @param   integer   $year: the year
     * @param   integer   $month: the month
     * @param   string    $calendar_class: the css-class of the html-table
     * @return  string    the html code
     */
    public function getMonthView($year = null, $month = null, $calendar_class = 'calendar')
    {

    //-------------- override class values if year/month are passed directly
        $year = (is_null($year)) ? $this->year : $year;
        $month = (is_null($month)) ? $this->month : str_pad($month, 2, '0', STR_PAD_LEFT);
        if ($month > 12) {
            $month = str_pad($month - 12, 2, '0', STR_PAD_LEFT);
            $year = $year + 1;
        } elseif ($month < 1) {
            $month = str_pad($month + 12, 2, '0', STR_PAD_LEFT);
            $year = $year - 1;
        }

        //---------------------------------------- get booked days of this month
        $this->setBookedDates($year, $month);

        //------------------------------------------------------- get week start
        $week_start = $this->getOption('week_start');
        //------------------------------------------- create first date of month
        $month_start_date = strtotime($year .'-'. $month .'-01');
        //------------------------- first day of month falls on what day of week
        $first_day_falls_on = $this->getDayOfWeek($month_start_date);
        //----------------------------------------- find number of days in month
        $days_in_month = date('t', $month_start_date);
        //-------------------------------------------- create last date of month
        $month_end_date = strtotime($year .'-'. $month .'-'. $days_in_month);
        //----------------------- calc offset to find number of cells to prepend
        $start_week_offset = $first_day_falls_on - $week_start;
        $prepend = ($start_week_offset < 0) ? 7 - abs($start_week_offset) : $start_week_offset;
        //-------------------------- last day of month falls on what day of week
        $last_day_falls_on = $this->getDayOfWeek($month_end_date);

        //------------------------------------------------- start table, caption
        $output  = '<table class="'. $calendar_class .'" cellspacing="0">'."\n";
        $output .= '<caption>'. ucfirst(strftime('%B %Y', $month_start_date)) .'</caption>'."\n";


        $col = '';
        $th = '';
        for ($i=1, $j=$week_start ,$t=(3+$week_start)*86400; $i<=7; $i++, $j++, $t+=86400) {
            $localized_day_name = strftime('%A', $t);
            $day_name = strtolower(date('l', $t));
            $col .= '<col class="'. $day_name .'" />';
            $th .= '<th scope="col" title="'. ucfirst($localized_day_name) .'">'. strtoupper($localized_day_name{0}) .'</th>';
            $j = ($j == 7) ? 0 : $j;
        }

        //------------------------------------------------------- markup columns
        $output .= $col."\n";

        $headstyle = '';
        if (rex::isBackend())
        {
          $headstyle = 'class="bg-primary"'; 
        }
      
        //----------------------------------------------------------- table head
        $output .= '<thead'. $headstyle .'>'."\n";
        $output .= '<tr>';
        $output .= $th;
        $output .= '</tr>'."\n";
        $output .= '</thead>'."\n";

        //---------------------------------------------------------- start tbody
        $output .= '<tbody>'."\n";
        $output .= '<tr>';

        //----------------------------------------------- initialize row counter
        $rows = 1;

        //--------------------------------------------------- pad start of month
        for ($i = 1; $i <= $prepend; $i++) {
            $output .= '<td class="pad">&nbsp;</td>';
        }

        //--------------------------------------------------- loop days of month
        for ($day = 1, $cell = $prepend + 1; $day <= $days_in_month; $day++, $cell++) {

      //-------- begin next row, if is first cell and not also the first day
            if ($cell == 1 && $day != 1) {
                $rows++;
                $output .= '<tr>';
            }

            //---------------- zero pad day and create date string for comparisons
            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
            $day_date = $year.'-'.$month.'-'.$day;

            //---------------------------- compare day and add classes for matches
            if ($this->getOption('mark_today') == true && $day_date == date('Y-m-d')) {
                $classes[] = $this->getOption('today_date_class');
            }
            if ($this->getOption('mark_passed') == true && $day_date < date('Y-m-d')) {
                $classes[] = $this->getOption('passed_date_class');
            }
            if (is_array($this->booked_dates)) {
                if (array_key_exists($day_date, $this->booked_dates)) {
                    $classes[] = 'booked-'.$this->booked_dates[$day_date];
                }
            }

            //------------------- loop matching class conditions, format as string
            if (isset($classes)) {
                $day_class = ' class="'.implode(' ', $classes).'"';
            } else {
                $day_class = '';
            }

            //------------------------------------ start table cell, apply classes
            $output .= '<td'.$day_class.' title="'.ucwords(strftime('%A, %e. %B %Y', strtotime($day_date))).'">';

            //------------------------------------------- unset to keep loop clean
            unset($day_class, $classes);

            //------------------------------------------- add link if in edit mode
            if ($this->mode == 'edit') {
                $output .= '<a href="'. $this->getOption('base_link').'&amp;date='.$day_date.'">'. (int) $day.'</a>';
            } else {
                $output .= (int) $day;
            }

            //--------------------------------------------------- close table cell
            $output .= '</td>';

            //--------- if this is the last cell, end the row and reset cell count
            if ($cell == 7) {
                $output .= '</tr>'."\n";
                $cell = 0;
            }
        }

        //----------------------------------------------------- pad end of month
        if ($cell > 1) {
            for ($i = $cell; $i <= 7; $i++) {
                $output .= '<td class="pad">&nbsp;</td>';
            }
            $output .= '</tr>'."\n";
        }

        //----------------------------------------- add a fake row if neccessary
        if ($this->getOption('table_six_rows') == true && $rows <= 5) {
            $output .= '<tr class="pad-row">';
            $output .= str_repeat('<td class="pad">&nbsp;</td>', 7);
            $output .= '</tr>'."\n";
        }



        //------------------------------------------------ close tbody and table
        $output .= '</tbody>'."\n";
        $output .= '</table>'."\n";

        //--------------------------------------------------------------- return
        return $output;
    }

    /**
     * Get a period as headline
     *
     * @access  public
     *
     * @param   integer   $view: the number of months to show
     * @param   integer   $year: the year
     * @param   integer   $month: the month
     * @return  string    the html code
     */
    public function getPeriod($view = 1, $year = null, $month = null)
    {
        global $REX;

        //-------------- override class values if year/month are passed directly
        $year = (is_null($year)) ? $this->year : $year;
        $month = (is_null($month)) ? $this->month : $month;
        $view = max(1, $view);

        //---------------------------------- create timestamp for current period
        $start_date = gmmktime(0, 0, 0, $month, 1, $year);
        $end_date = gmmktime(0, 0, 0, $month + $view - 1, 1, $year);
        if ($start_date < $end_date) {
            $date_txt = gmstrftime('%B %Y', $start_date).' - '.gmstrftime('%B %Y', $end_date);
        } else {
            $date_txt = gmstrftime('%B %Y', $start_date);
        }

        //--------------------------------------------------------- start output
        $output  = '<h3 class="period">'.$date_txt.'</h3>'."\n";

        //--------------------------------------------------------------- return
        return $output;
    }

    /**
     * Get the legend for the booking states
     *
     * @access  public
     *
     * @param   null/array   $labels: the labels for the booking states
     * @return  string    the html code
     */
    public function getLegend($labels = null)
    {

    //--------------------------------------------------------- start output
        $output  = '';
        if (is_array($labels) && count($labels) > 0) {
            $output  .= '<ul class="legend">';
            foreach ($labels as $state => $label) {
                if ($label != '') {
                    $output  .= '<li class="booked-'.$state.'">'.$label.'</li>';
                }
            }
            $output  .= '</ul>'."\n";
        }

        //--------------------------------------------------------------- return
        return $output;
    }

    /**
     * Get a prev-next-navigation
     *
     * @access  public
     *
     * @param   integer   $view: the number of months to show
     * @param   integer   $year: the year
     * @param   integer   $month: the month
     * @return  string    the html code
     */
    public function getNav($view = 1, $year = null, $month = null)
    {
        global $REX;

        //-------------- override class values if year/month are passed directly
        $year = (is_null($year)) ? $this->year : $year;
        $month = (is_null($month)) ? $this->month : $month;

        //--------------------------------- create timestamp for previous period
        $prev_start_date = gmmktime(0, 0, 0, $month - $view, 1, $year);
        $prev_end_date = gmmktime(0, 0, 0, $month - 1, 1, $year);
        if ($prev_start_date < $prev_end_date) {
            $prev_date_txt = gmstrftime('%B %Y', $prev_start_date).' - '.gmstrftime('%B %Y', $prev_end_date);
        } else {
            $prev_date_txt = gmstrftime('%B %Y', $prev_start_date);
        }
        //-------------------------------- create timestamp for displayed period
        $curr_start_date = gmmktime(0, 0, 0, $month, 1, $year);
        $curr_end_date = gmmktime(0, 0, 0, $month + $view - 1, 1, $year);
        if ($curr_start_date < $curr_end_date) {
            $curr_date_txt = gmstrftime('%B %Y', $curr_start_date).' - '.gmstrftime('%B %Y', $curr_end_date);
        } else {
            $curr_date_txt = gmstrftime('%B %Y', $curr_start_date);
        }
        //------------------------------------- create timestamp for next period
        $next_start_date = gmmktime(0, 0, 0, $month + $view, 1, $year);
        $next_end_date = gmmktime(0, 0, 0, $month + $view + $view - 1, 1, $year);
        if ($next_start_date < $next_end_date) {
            $next_date_txt = gmstrftime('%B %Y', $next_start_date).' - '.gmstrftime('%B %Y', $next_end_date);
        } else {
            $next_date_txt = gmstrftime('%B %Y', $next_start_date);
        }
        //---------------------------------- create hrefs for prev + next period
        if ($this->mode == 'edit') {
            $prev_link = $this->getOption('base_link').'&amp;date='.date('Y-m-d', $prev_start_date);
            $next_link = $this->getOption('base_link').'&amp;date='.date('Y-m-d', $next_start_date);
        } else {
            $now_start_date = gmmktime(0, 0, 0, date('m'), 1, date('Y'));
            if ($this->mode == 'view' && $prev_end_date < $now_start_date) {
                $prev_link = false;
            } else {
                $prev_link = rex_getUrl($REX['ARTICLE_ID'], $REX['CUR_CLANG'], array('date' => date('Y-m-d', $prev_start_date)));
            }
            $next_link = rex_getUrl($REX['ARTICLE_ID'], $REX['CUR_CLANG'], array('date' => date('Y-m-d', $next_start_date)));
        }

        //----------------------------------------------------------- start list
        $prev_date_txt = $prev_date_txt;
        $next_date_txt = $next_date_txt;
        $curr_date_txt = $curr_date_txt;

        $output  = '<ul class="prev_next"><li class="prev">';
        $now_start_date = gmmktime(0, 0, 0, date('m'), 1, date('Y'));
        if ($prev_link === false) {
            $output .= '&nbsp;';  // don't display a link for the past in the frontend
        } else {
            $output .= '<a href="'. $prev_link.'" title="'.$prev_date_txt.'">'.$prev_date_txt.'</a>';
        }
        $output .= '</li><li class="curr">';
        $output .= '<span>'.$curr_date_txt.'</span>';
        $output .= '</li><li class="next">';
        $output .= '<a href="'. $next_link.'" title="'.$next_date_txt.'">'.$next_date_txt.'</a>';
        $output .= '</li></ul>'."\n";

        //--------------------------------------------------------------- return
        return $output;
    }
}
