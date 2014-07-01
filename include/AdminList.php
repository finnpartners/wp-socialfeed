<?php
  
if(!class_exists('WP_List_Table_Custom')){
    require_once( plugin_dir_path( __FILE__ ) . 'class-wp-list-table.php' );
}

class AdminList extends WP_List_Table_Custom {
	
	var $_query;
	public function setQuery($value){
		$this->_query = $value;
	}
	
	public function getQuery(){
		return $this->_query;
	}
	
	var $_columns;
/*
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'title'     => 'Title',
            'rating'    => 'Rating',
            'director'  => 'Director'
        );
*/

	public function setColumns($value){
		foreach($value as $id => $name){
			$this->_first_column = $id;
			break;
		}
		
		$this->setSortableColumns(array_keys($value));
		$value 				     = array_merge(array('cb' => '<input type="checkbox" />'), $value);
		$this->_columns 		 = $value;
		//$this->_sortable_columns = array_keys($value);
		
	}
	var $_first_column;
	
	var $_table;
	public function setTable($value){
		$this->_table = $value;
	}
	public function getTable(){
		return $this->_table;
	}
	
	var $_sort_dir;
	public function setSortDir($value){
		$this->_sort_dir = $value;
	}
	
	var $_sort_column;
	public function setSortColumn($value){
		$this->_sort_column = $value;
	}
	
	var $_sortable_columns;
/*
        $sortable_columns = array(
            'title'     => array('title',false),     //true means it's already sorted
            'rating'    => array('rating',false),
            'director'  => array('director',false)
        );
*/
	public function setSortableColumns($value){
		$cols = array();
		foreach($value as $c){
			$cols[$c] = array($c, false);
		}
		$this->_sortable_columns = $cols;
	}

	var $_bulk_actions = array('delete' => 'Delete');
	public function setBulkActions($value){
		$this->_bulk_actions = $value;
	}

	var $_rows_per_page = 10;
	public function setRowsPerPage($value){
		$this->_rows_per_page = $value;
	}

	var $_title;
	public function setTitle($value){
		$this->_title = $value;
	}

	public function getTitle(){
		return $this->_title;
	}
	
	var $_actions = array();
	public function addAction($value){
		$this->_actions[] = $value;
	}
	
	
	

    /** ************************************************************************
     * REQUIRED. Set up a constructor that references the parent constructor. We 
     * use the parent reference to set some default configs.
     ***************************************************************************/
    function __construct(){
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'movie',     //singular name of the listed records
            'plural'    => 'movies',    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );    
        
    }


    /** ************************************************************************
     * Recommended. This method is called when the parent class can't find a method
     * specifically build for a given column. Generally, it's recommended to include
     * one method for each column you want to render, keeping your package class
     * neat and organized. For example, if the class needs to process a column
     * named 'title', it would first see if a method named $this->column_title() 
     * exists - if it does, that method will be used. If it doesn't, this one will
     * be used. Generally, you should try to use custom column methods as much as 
     * possible. 
     * 
     * Since we have defined a column_title() method later on, this method doesn't
     * need to concern itself with any column with a name of 'title'. Instead, it
     * needs to handle everything else.
     * 
     * For more detailed insight into how columns are handled, take a look at 
     * WP_List_Table::single_row_columns()
     * 
     * @param array $item A singular item (one full row's worth of data)
     * @param array $column_name The name/slug of the column to be processed
     * @return string Text or HTML to be placed inside the column <td>
     **************************************************************************/
    function column_default($item, $column_name){
    	
    	if($column_name == $this->_first_column){
    		
			foreach($this->_actions as $a){
	        	$actions[$a] = sprintf('<a href="?page=%s&action=%s&id=%s">'.ucfirst($a).'</a>',$_REQUEST['page'], $a, $item['id']);
			}
	        $actions['delete'] = sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>',$_REQUEST['page'],'delete',$item['id']);
	        
	        //Return the title contents
	        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
	            /*$1%s*/ $item[$this->_first_column],
	            /*$2%s*/ $item['id'],
	            /*$3%s*/ $this->row_actions($actions)
	        );
    		
		}else{
    		return $item[$column_name];
		}
    	
        /*switch($column_name){
            case 'rating':
            case 'director':
                return $item[$column_name];
            default:
                return print_r($item,true); //Show the whole array for troubleshooting purposes
        }
        */
    }


    /** ************************************************************************
     * Recommended. This is a custom column method and is responsible for what
     * is rendered in any column with a name/slug of 'title'. Every time the class
     * needs to render a column, it first looks for a method named 
     * column_{$column_title} - if it exists, that method is run. If it doesn't
     * exist, column_default() is called instead.
     * 
     * This example also illustrates how to implement rollover actions. Actions
     * should be an associative array formatted as 'slug'=>'link html' - and you
     * will need to generate the URLs yourself. You could even ensure the links
     * 
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
/*    function column_title($item){
        
        //Build row actions
        $actions = array(
            'edit'      => sprintf('<a href="?page=%s&action=%s&movie=%s">Edit</a>',$_REQUEST['page'],'edit',$item['ID']),
            'delete'    => sprintf('<a href="?page=%s&action=%s&movie=%s">Delete</a>',$_REQUEST['page'],'delete',$item['ID']),
        );
        
        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
            /*$1%s* / $item['title'],
            /*$2%s* / $item['ID'],
            /*$3%s* / $this->row_actions($actions)
        );

    }
*/


    /** ************************************************************************
     * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
     * is given special treatment when columns are processed. It ALWAYS needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item['ID']                //The value of the checkbox should be the record's id
        );
    }


    /** ************************************************************************
     * REQUIRED! This method dictates the table's columns and titles. This should
     * return an array where the key is the column slug (and class) and the value 
     * is the column's title text. If you need a checkbox for bulk actions, refer
     * to the $columns array below.
     * 
     * The 'cb' column is treated differently than the rest. If including a checkbox
     * column in your table you must create a column_cb() method. If you don't need
     * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_columns(){
/*
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'title'     => 'Title',
            'rating'    => 'Rating',
            'director'  => 'Director'
        );
*/
        return $this->_columns;
    }


    /** ************************************************************************
     * Optional. If you want one or more columns to be sortable (ASC/DESC toggle), 
     * you will need to register it here. This should return an array where the 
     * key is the column that needs to be sortable, and the value is db column to 
     * sort by. Often, the key and value will be the same, but this is not always
     * the case (as the value is a column name from the database, not the list table).
     * 
     * This method merely defines which columns should be sortable and makes them
     * clickable - it does not handle the actual sorting. You still need to detect
     * the ORDERBY and ORDER querystring variables within prepare_items() and sort
     * your data accordingly (usually by modifying your query).
     * 
     * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
     **************************************************************************/
    function get_sortable_columns() {
/*
        $sortable_columns = array(
            'title'     => array('title',false),     //true means it's already sorted
            'rating'    => array('rating',false),
            'director'  => array('director',false)
        );
*/
        return $this->_sortable_columns;
    }


    /** ************************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     * 
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     * 
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     * 
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_bulk_actions() {
/*
        $actions = array(
            'delete'    => 'Delete'
        );
*/
        //return $this->_bulk_actions;
    }


    /** ************************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    function process_bulk_action() {
		global $wpdb;
		if($this->_bulk_action_callback && function_exists($this->_bulk_action_callback)){
			call_user_func($this->_bulk_action_callback, $this->current_action);
		}   
		
		//die($this->current_action);
		if($_REQUEST["action"] == "delete"){
			$wpdb->query($wpdb->prepare("DELETE FROM ".$this->getTable()." WHERE id = %d", $_REQUEST["id"]));
		}
		/*     
        //Detect when a bulk action is being triggered...
        if( 'delete'===$this->current_action() ) {
            wp_die('Items deleted (or they would be if we had items to delete)!');
        }
        */
        
    }


    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     * 
     * @global WPDB $wpdb
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    function prepare_items() {
        global $wpdb; //This is used only if making any database queries
        
        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        
        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        
        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();
        
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : (($this->_sort_column) ? $this->_sort_column : $this->_first_column); //If no sort, default to title
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : (($this->_sort_dir) ? $this->_sort_dir : 'asc'); //If no order, default to asc
        $this->setQuery($this->getQuery() . " ORDER BY $orderby $order");
        
        $data = $wpdb->get_results($this->getQuery(), ARRAY_A);
                
        
        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         * 
         * In a real-world situation involving a database, you would probably want 
         * to handle sorting by passing the 'orderby' and 'order' values directly 
         * to a custom query. The returned data will be pre-sorted, and this array
         * sorting technique would be unnecessary.
         */
         /*
        function usort_reorder($a,$b, $default_column, $default_dir){
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : $default_column; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : $default_dir; //If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
            return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
        }
        usort($data, 'usort_reorder', (($this->_sort_column) ? $this->_sort_column : $this->_first_column), (($this->_sort_dir) ? $this->_sort_dir : 'asc'));
        */
                
        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently 
         * looking at. We'll need this later, so you should always include it in 
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        
        /**
         * REQUIRED for pagination. Let's check how many items are in our data array. 
         * In real-world use, this would be the total number of items in your database, 
         * without filtering. We'll need this later, so you should always include it 
         * in your own package classes.
         */
        $total_items = count($data);
        
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to 
         */
        $data = array_slice($data,(($current_page-1)*$this->_rows_per_page), $this->_rows_per_page);
        
        
        
        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $data;
        
        
        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $this->_rows_per_page,         //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$this->_rows_per_page)   //WE have to calculate the total number of pages
        ) );
    }

    public function render(){
	    $this->prepare_items();
	    
	    ?>
	    <div class="wrap">
	    <?
	    if($this->getTitle()){
	    	?><h2><?= esc_html($this->getTitle()) ?></h2><?
	    }
		?>	        
	        <form id="movies-filter" method="get">
	            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
	            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
	            <!-- Now we can render the completed list table -->
	            <?php $this->display() ?>
	        </form>
	        
	    </div>
	    <?php
	}

}

