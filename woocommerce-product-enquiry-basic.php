<?php
/*
Plugin Name: WooCommerce Product Enquiry
Description: This plugin will add a simple enquiry form to your product page.
Managing your enquiries is now very easy for you.
Author: MingoCommerce
Author URI: http://www.mingocommerce.com
Version: 2.7.0
Plugin URI: https://wordpress.org/plugins/woo-product-enquiry/
WC tested up to: 4.4.1
*/
class MGC_Product_Enquery{
	var $id;
	var $fields;
	function __construct(){
		$this->init();
		add_filter('woocommerce_product_tabs', array($this, 'enquery_tab'));
		add_filter('product_enquery_fields', array($this, 'check_enquery_field_ids'), 20);		
		add_action('init', array($this, 'register_post_types'));
		add_action('init', array($this, 'validate_enquery_form'));
	}
	
	function init(){
		$this->id		=	'mcg_prd_enq';
		$this->fields	=	array(
			'name'	=>	array(
				'label'	=>	__('Name', 'woocommerce'),
				'type'	=>	'text',
				'validation'	=>	array('required')
			),
			'email'	=>	array(
				'label'	=>	__('Email', 'woocommerce'),
				'type'	=>	'text',
				'validation'	=>	array('required','email')
			),
			'phone'	=>	array(
				'label'	=>	__('Phone', 'woocommerce'),
				'type'	=>	'text',
				'validation'	=>	array('required','phone')
			),
			'enquiry'	=>	array(
				'label'	=>	__('Enquiry', 'woocommerce'),
				'type'	=>	'textarea',
				'validation'	=>	array('required')
			),
			'mcg_enq_submit'	=>	array(
				'label'	=>	__('Submit', 'woocommerce'),
				'type'	=>	'submit',
			),
		);
	}
	
	
	
	function register_post_types(){
		$enq_args	=	array(
			'label'	=>	'Product Enquiry',
			'show_ui'	=>	true,
			'show_in_menu'	=>	'edit.php?post_type=product',
		);
		register_post_type('product_enquiry', $enq_args );
	}
	
	function enquery_tab($tabs){
		$tab_name 	= MGC_Product_Enquery_Admin::get_option('tab_name');
		$tab_name	=	$tab_name ? $tab_name : __('Enquiry','woocommerce');
		$tab_priority 	= 	MGC_Product_Enquery_Admin::get_option('tab_priority');
		$tab_priority	=	$tab_priority ? $tab_priority : 40;
		$tabs[$this->id.'enquery_tab'] = array(
			'title'     => $tab_name,
			'priority'  => $tab_priority,
			'callback'  => array($this, 'enquery_tab_html'),
		);
		return $tabs;
	}
	
	function enquery_tab_html(){
		global $product;
		
		$fields	=	apply_filters('product_enquery_fields', $this->fields);
		$html	=	'';
		$html	.=	'<form method="POST">';
		$html	.=		'<input type="hidden" name="product_id" value="'.$product->get_id().'">';
		$html	.=		'<table>';
			foreach($fields as $field){
				$html	.=	$this->field_row($field);
			}
		$html	.=		'</table>';
		$html	.=	'</form>';
		do_action('product_enquiry_before_form', $product);
		echo $html;
		do_action('product_enquiry_after_form', $product);
	}
	
	function field_row($field){
		
		if(isset($_POST[$field['id']])){
			if(isset($field['validation']) && in_array('email',$field['validation'])){
				$field['value']	=	sanitize_email($_POST[$field['id']]);
			}else{
				if($field['type']	==	'textarea'){
					$field['value']	=	sanitize_textarea_field($_POST[$field['id']]);
				}else{
					$field['value']	=	sanitize_text_field($_POST[$field['id']]);
				}
			}
		}else{
			$field['value']	=	isset($field['default_value']) ? $field['default_value'] : '';
		}
		
		//$field['value'] = esc_html($field['value']); //escaping unwanted script/ html codes from printing inside input field
				
		$output	=	'';
		$output	.=	'<tr>';		
		
			switch($field['type']){
				case 'text': default:
					$output	.=	'<td>'.$field['label'].'</td>';
					$output	.=	'<td><input type="text" name="'.$field['id'].'" value="'.esc_attr($field['value']).'"></td>';
					break;
					
				case 'textarea':
					$output	.=	'<td>'.$field['label'].'</td>';
					$output	.=	'<td><textarea name="'.$field['id'].'">'.esc_textarea($field['value']).'</textarea></td>';
					break;
					
				case 'submit':
					$output	.=	'<td colspan="2"><input type="submit" name="'.$field['id'].'" value="'.esc_attr($field['label']).'"></td>';
					break;
			}
			
		$output	.=	'</tr>';
		
		return $output;
	}
	
	function check_enquery_field_ids($fields){
		if(is_array($fields)){
			foreach($fields as $key => $field){
				if(!isset($field['id'])){
					$fields[$key]['id']	=	$key;
				}
			}
		}
		return $fields;
	}
	
	function validate_enquery_form(){
		if(!isset($_POST['mcg_enq_submit']))
			return;
		$fields	=	apply_filters('product_enquery_fields', $this->fields);
		if($this->validate_fields($fields)){
			$product	=	wc_get_product((int)$_POST['product_id']);
			
			$settings_email = MGC_Product_Enquery_Admin::get_option('email_to');
			$admin_mail =  get_option('admin_email');			
			$receiver_email	=	$settings_email ? $settings_email : $admin_mail;
			
			$email_subject = MGC_Product_Enquery_Admin::get_option('email_subject');
			
			$email_subject	=	$email_subject ? $email_subject : 'New enquery posted on %%product_name%%';			
			$email_subject	=	str_replace(array('%%product_name%%'), array($product->get_title()), $email_subject);
			
			$mail_table_fields	=	$fields;
			unset($mail_table_fields['mcg_enq_submit']);
			$email_body	=	'A new equery is posted regarding '.$product->get_title().'. Information about the query is as followed --- </br>';
			$email_body	.=	'<table>';
				foreach($mail_table_fields as $mail_table_field){
					//sanitizing data before sending to mail
					if(in_array('email',$mail_table_field['validation'])){
						$mail_table_field['value']	=	sanitize_email($_POST[$mail_table_field['id']]);
					}else{
						if($mail_table_field['type']	==	'textarea'){
							$mail_table_field['value']	=	sanitize_textarea_field($_POST[$mail_table_field['id']]);
						}else{
							$mail_table_field['value']	=	sanitize_text_field($_POST[$mail_table_field['id']]);
						}
					}
					
					
					$email_body	.=	'<tr>';
						$email_body	.=	'<td>'.$mail_table_field['label'].'</td><td>:</td><td>'.$mail_table_field['value'].'</td>';
					$email_body	.=	'</tr>';
				}
			$email_body	.=	'</table>';
			
			$receiver_email	=	apply_filters('mgc_pe_sender_email', $receiver_email, $product);
			
			add_filter( 'wp_mail_content_type', array($this, 'mail_set_content_type_html'));
			$send_mail = wp_mail($receiver_email, $email_subject, $email_body);
			remove_filter( 'wp_mail_content_type', array($this, 'mail_set_content_type_html') );
			$data_to_be_saved	=	array(
				'_pe_product'	=>	$product->get_id(),
			);
			foreach($mail_table_fields as $mail_table_field){
				$data_to_be_saved['_pe_'.$mail_table_field['id']]	=	sanitize_text_field($_POST[$mail_table_field['id']]); // sanitizing filed values before saving in database
			}
			$new_enquiry	=	array(
				'post_type'		=>	'product_enquiry',
				'post_status'	=>	'publish',
				'post_title'	=>	'Enquiry on '.$product->get_title(),
				'meta_input'	=>	$data_to_be_saved
			);
			$post_id		=	wp_insert_post($new_enquiry, true);
			
			if($send_mail){
				wc_add_notice(__('Your query posted successfully. We will get back to you soon.'),'success');
				unset($_POST);
			}else{
				wc_add_notice(__('Error occured while sending email. Please retry after sometime.'));
			}
			
		}
		return true;
	}
	
	function validate_fields($fields){
		$valid 			=	true;
		foreach($fields as $key	=>	$field){
			if(isset($field['validation']) && is_array($field['validation'])){
				$field['value']	=	sanitize_text_field($_POST[$key]); // sanitizing because if after sanitization, value becomes blank then want to throw required field error
				foreach($field['validation'] as $validation){
					$v	=	$this->validate_field($field, $validation);
					if(!$v['status']===true){
						wc_add_notice($v['message'],'error');
						$valid	=	false;
						break;
					}
				}
				
			}
		}
		return $valid;
	}
	
	function validate_field($field, $validation){
		$message			=	'';
		$value				=	$field['value'];
		$validated 			=	true;
		$defalut_message	=	'';
		
		if(is_array($validation)){
			$validation_type	=	key($validation);
			$message			=	current($validation);
		}else{
			$validation_type = $validation;
		}
		
		switch($validation_type){
			
			case 'required':
				if(empty($value)){
					$defalut_message	=	sprintf(__('%s: This is a required field', 'woocommerce'),$field['label']);
					$validated			=	false;
				}
				break;
			
			case 'email':
				if(!WC_Validation::is_email($value)){
					$defalut_message	=	sprintf(__('%s: This is not a valid email address', 'woocommerce'),$field['label']);
					$validated			=	false;
				}
				break;
				
			case 'phone':
				if(!WC_Validation::is_phone($value)){
					$defalut_message	=	sprintf(__('%s: This is not a valid phone number', 'woocommerce'),$field['label']);
					$validated			=	false;
				}
				break;
				
			default:
				break;
		}
		
		$message	=	$message ? $message : $defalut_message;		
		return array('status'=>$validated, 'message'=>$message);
	}
	
	function mail_set_content_type_html(){
		return "text/html";
	}
}

add_filter('mgc_pe_sender_email', 'MGC_Product_Enquery_WVC_Sender', 10, 3);
	
function MGC_Product_Enquery_WVC_Sender( $seneder_mail, $product ){
		
	if(class_exists('WCV_Vendors')){ // This class denotes vendor plugin is installed
		
		$author = WCV_Vendors::get_vendor_from_product( $product->get_id() );
		
		if(WCV_Vendors::is_vendor( $author )){
			
			$author_data	=	get_userdata( $author );
			$vendor_email	=	$author_data->data->user_email;
			if( $vendor_email ){
				
				$seneder_mail	=	$vendor_email;
				
			}
		}
		
	}
	
	return $seneder_mail;
	
}

new MGC_Product_Enquery();


class MGC_Product_Enquery_Admin{
	
	function __construct(){
		$this->register_menues();
		add_filter('manage_product_enquiry_posts_columns' , array($this, 'add_enquiry_column_head'));
		add_filter('manage_product_enquiry_posts_custom_column' , array($this, 'enquiry_column_data'), 10, 2);
	}
	
	function register_menues(){
		add_action('admin_menu', array($this, 'add_menues') );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links') );
		add_action( 'admin_init', array($this, 'register_settings') );
	}
	
	function add_menues(){
		add_submenu_page( 'woocommerce', 'Product Enquiry Form', 'Product Enquiry Form', 'manage_options', 'producy_enquiry_form_settings_page', array($this, 'settings_page') );
	}
	
	function add_enquiry_column_head( $columns ){
		if(isset($columns['title'])){
			$columns['title']	=	'Product';
		}
		$new_columns	=	array(
			'cb'		=>	$columns['cb'],
			'title'			=>	$columns['title'],
			'_pe_name'		=>	'Name',
			'_pe_email'		=>	'Email',
			'_pe_phone'		=>	'Phone',
			'_pe_enquiry'	=>	'Enquiry',
		);
		$columns	=	array_merge( $new_columns, $columns	);	
		return $columns;
	}
	
	function enquiry_column_data($column_name, $post_id){
		switch($column_name){
			case '_pe_name':
				echo esc_html(get_post_meta($post_id, '_pe_name', 1)); // escaping html and js before printing to admin panel columns
				break;
			
			case '_pe_email':
				$email	=	esc_html(get_post_meta($post_id, '_pe_email', 1));
				echo $email;
				echo ' <a href="mailto:'.$email.'">Reply</a>';
				break;
				
			case '_pe_phone':
				echo esc_html(get_post_meta($post_id, '_pe_phone', 1));
				break;
				
			case '_pe_enquiry':
				echo esc_html(get_post_meta($post_id, '_pe_enquiry', 1));
				break;
			
			default:
				break;
		}
	}
	
	function action_links($links){
		$links[]	=	'<a href="' . admin_url( 'admin.php?page=producy_enquiry_form_settings_page' ) . '">'.__('Settings', 'mingocommerce').'</a>';
		return $links;
	}
	
	function settings_page(){
		?>
		<div class="wrap">
			<h1>Enquiry Form Settings</h1>
			<form method="post" action="options.php">
			<?php settings_fields( 'mgc-product-enq' );?>
			<?php do_settings_sections( 'mgc-product-enq' );?>
			<?php 
			$settings_email = MGC_Product_Enquery_Admin::get_option('email_to');
			$admin_mail =  get_option('admin_email');			
			$receiver_email	=	$settings_email ? $settings_email : $admin_mail;			
			
			$email_subject = MGC_Product_Enquery_Admin::get_option('email_subject');			
			$email_subject	=	$email_subject ? $email_subject : 'New enquery posted on %%product_name%%';	
			
			$tab_name 	= MGC_Product_Enquery_Admin::get_option('tab_name');
			$tab_name	=	$tab_name ? $tab_name : 'Enquiry';
			
			$tab_priority 	= MGC_Product_Enquery_Admin::get_option('tab_priority');
			$tab_priority	=	$tab_priority ? $tab_priority : 40;
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Tab Name</th>
					<td><input type="text" size="50" name="mpe_tab_name" value="<?php echo esc_attr( $tab_name ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Tab Priority</th>
					<td><input type="text" size="50" name="mpe_tab_priority" value="<?php echo esc_attr( $tab_priority ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">To Email</th>
					<td><input type="text" size="50" name="mpe_email_to" value="<?php echo esc_attr( $receiver_email ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Email Subject</th>
					<td><input type="text" size="50" name="mpe_email_subject" value="<?php echo esc_attr( $email_subject ); ?>" /></td>
				</tr>
				<tr valign="top">
					<td colspan="2"><i>Mail body will contain customer's enquiry. Option to customize that is next version. To get any quick customization just raise a ticket to <a href="http://www.mingocommerce.com/contact-us/">Mingocommerce Website</a></i></td>
				</tr>
			</table>
			<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	public static function get_option($option_name){
		return get_option('mpe_'.$option_name);
	}
	
	function register_settings(){
		register_setting( 'mgc-product-enq', 'mpe_email_to' );
		register_setting( 'mgc-product-enq', 'mpe_email_subject' );
		register_setting( 'mgc-product-enq', 'mpe_tab_name' );
		register_setting( 'mgc-product-enq', 'mpe_tab_priority' );
	}
}

new MGC_Product_Enquery_Admin();
