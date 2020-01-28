<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

class FW_Extension_Woffice_Birthdays extends FW_Extension {

	/**
	 * @internal
	 */
	public function _init() {
		add_action('fw_extensions_before_deactivation', array($this, 'woffice_birthdays_delete_field'));
		add_action('fw_settings_form_saved', array($this, 'woffice_birthdays_add_field'));
	}
	
	/**
	 * This function returns an array of the members birthdays :
     * Only today and upcoming birthdays sorted in ascending order
     *
	 * array(
	 * 	  id_member => array('datetime' => DateTime Object);
	 * )
	 */
	public function woffice_birthdays_get_array() {
	
	 	/* We get all the users ID */
		$woffice_wp_users = get_users(array('fields' => array('ID')));
		/*Array returned*/
		$members_birthdays = array();

		// Get the Birthday field name
		$field_name = $this->woffice_birthdays_field_name();
		$field_name = str_replace( "'", "\'", $field_name);

		// Get the Birthday field ID
		$field_id = xprofile_get_field_id_from_name( $field_name );

		/* We check if the member has a birthday set */
		foreach($woffice_wp_users as $woffice_wp_user) {

            $birthday_string = maybe_unserialize( BP_XProfile_ProfileData::get_value_byid( $field_id, $woffice_wp_user->ID ) );

			if(!empty($birthday_string)){
				
				/*We transform the string in a date*/
				$birthday = DateTime::createFromFormat('Y-m-d H:i:s', $birthday_string);

				/**
				 * Filter if the current birthday (in the birthdays widget) can be displayed
				 *
				 * @param bool $is_displayed
				 * @param int $user_id
				 * @param DateTime $birthday
				 */
				$display_this_birthday = apply_filters( 'woffice_display_this_birthday', true, $woffice_wp_user->ID, $birthday);

				if ( $birthday != false && $display_this_birthday ){

					$celebration_year = ( date('md', $birthday->getTimestamp()) >= date('md') ) ? date('Y') : date('Y', strtotime('+1 years') );

				    $years_old = (int)$celebration_year - (int)date("Y", $birthday->getTimestamp());

				    $celebration_string = $celebration_year . date('md', $birthday->getTimestamp());

					/* We add it to the array */
					$members_birthdays[$woffice_wp_user->ID] = array(
                        'datetime' => $birthday,
						'next_celebration_comparable_string' => $celebration_string,
						'years_old' => $years_old
					);
				}
			}
			
		}

        uasort($members_birthdays, array($this, "date_comparison"));

		return $members_birthdays;
	}		 

	
	/**
	 * Custom function to search in our array in the function below (from http://stackoverflow.com/questions/4128323/in-array-and-multidimensional-array)
	 */		
	public function woffice_in_multiarray($value, $array) {
		if(in_array($value, $array)) {
		  return true;
		}
		foreach($array as $item) {
		  if(is_array($item) && $this->woffice_in_multiarray($value, $item))
		       return true;
		}
		return false;
	}	

	/**
	 * It will generate the title for the front view
	 */
	public function woffice_birthdays_title($all_bithdays) { 
		
		if (!empty($all_bithdays)){
			$widget_title =  '<h3>'. __('Upcoming Birthdays','woffice') .'</h3>';
		}
		else {
			$widget_title = '<h3>'. __('Sorry no birthdays set...','woffice') .'</h3>';
		}

		/**
		 * Filter the title of the Birthdays widget
		 *
		 * @param string $widget_title The title of the widget
		 * @param array $all_bithdays the array of all birthdays
		 */
		return apply_filters('woffice_birthdays_widget_title', $widget_title, $all_bithdays);
	}
	
	/**
	 * It will generate the content for the front viw
	 */
	public function woffice_birthdays_content($all_bithdays) { 
		
		if (!empty($all_bithdays)){
			
            $max_items = $this->woffice_birthdays_to_display();
            $c = 0;

			$date_ymd = date('Ymd');

            foreach($all_bithdays as $user_id => $birthday) {
                if($c == $max_items)
                    break;

                $activation_key = get_user_meta($user_id, 'activation_key');
                if(empty($activation_key)) {
                    //if(date('m', $birthday["datetime"]->getTimestamp()) == $current_month && date('d', $birthday["datetime"]->getTimestamp()) > date('d') ) {
                    //if(date('md', $birthday["datetime"]->getTimestamp()) >= date('md') ) {
                    $name_to_display = woffice_get_name_to_display($user_id);

					$age = $birthday["years_old"];
	                //$age= apply_filters('woffice_birthdday_additional_age_checks', $age, $birthday);

					// We don't display negative ages
					if($age > 0) {
						echo '<li class="clearfix">';
						if (function_exists('bp_is_active')):
							echo '<a href="' . bp_core_get_user_domain($user_id) . '">';
							echo get_avatar($user_id);
							echo '</a>';
						else :
							echo get_avatar($user_id);
						endif;
						echo '<span class="birthday-item-content">';
						echo '<strong>' . get_userdata($user_id)->display_name . '</strong>';
						if ($this->woffice_birthdays_display_age() != 'nope') {
							echo ' <i>(' . $age . ')</i>';
						}
						echo ' ', _x('on', 'happy birthday ON 25-06', 'woffice');
						$date_format = $this->woffice_birthdays_date_format();
						$date_format = (!empty($date_format)) ? $date_format : 'F d';
						echo ' <span class="label">' . date_i18n($date_format, $birthday["datetime"]->getTimestamp()) . '</span>';
						$happy_birthday_label = '';
						if($birthday["next_celebration_comparable_string"] == $date_ymd)
							$happy_birthday_label = '<span class="label">' . __( 'Happy Birthday!', 'woffice') . '</span>';

						/**
						 * The label "Happy birthday", if today is the birthday of an user
						 *
						 * @param string $happy_birthday_label The text of the label (contains some HTML)
						 * @param int $user_id
						 */
						echo apply_filters( 'woffice_today_happy_birthday_label', $happy_birthday_label, $user_id );

						echo '</span>';
						echo '</li> Hey, dit is fucking vreemd!';

                        $c++;
					}
                }


            }

		}
	}
	
	/**
	 *  CREATE FUNCTIONS TO ADD THE BIRTHDAY FIELD TO XPROFILE
	 */
	public function woffice_birthdays_add_field() {

		if ( ! woffice_bp_is_active( 'xprofile' ) )
			return;

		// Get the Birthday field name
		$field_name = $this->woffice_birthdays_field_name();
		$field_name = str_replace( "'", "\'", $field_name);

		// Get the Birthday field ID
		$field_id = xprofile_get_field_id_from_name( $field_name );

		// If the field already exists, don't create it again
	    if ( !is_null($field_id) )
	        return;

		$insert_field = xprofile_insert_field(
	        array (
	            'field_group_id'  => 1,
				//'can_delete' => true,
				'type' => 'datebox',
				//'description' => __('We will only use it for the Birthday widget, so we can celebrate everyone\s birthday.','woffice'),
				'name' => $field_name,
				//'field_order'     => 1,
				//'is_required'     => false,
	        )
	    );
	    //fw_print($insert_field);

	}
	
	/**
	 * DELETE THE BIRTHDAY FIELD IN XPROFILE
	 */
	public function woffice_birthdays_delete_field($extensions) {
		/* ONLY IF IT's the BIRTHDAY extension */
		if (!isset($extensions['woffice-birthdays'])) {
	        return;
	    }
		global $bp;

		$field_name = $this->woffice_birthdays_field_name();

		$id = xprofile_get_field_id_from_name($field_name);
		xprofile_delete_field($id);
	}
	
	/**
	 * CREATE FUNCTIONS TO GET THE OPTION FROM THE SETTINGS
	 * @return yes or nope
	 */
	public function woffice_birthdays_display_age() {
		return fw_get_db_ext_settings_option( $this->get_name(), 'display_age' );
	}

	/**
	 * Get date format of birthday set in Birthday extension options
	 * @return string
	 */
	public function woffice_birthdays_date_format() {
		return fw_get_db_ext_settings_option( $this->get_name(), 'birthday_date_format' );
	}

    /**
     * Get the field's name
     * @return string
     */
    public function woffice_birthdays_field_name() {
        return fw_get_db_ext_settings_option( $this->get_name(), 'birthday_field_name' );
    }

    /**
     * Get the max number of the item to display
     * @return string
     */
    public function woffice_birthdays_to_display() {
        return fw_get_db_ext_settings_option( $this->get_name(), 'birthdays_to_display' );
    }

    /**
     * Used for order array of object, containing dates
     */
    private function date_comparison($a, $b) {
        return ( $a['next_celebration_comparable_string'] > $b['next_celebration_comparable_string'] );
    }
}