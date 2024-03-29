<?php
/*
// +----------------------------------------------------------------------+
// | Licenses and copyright acknowledgements are located at               |
// | http://www.sonsofskadi.net/wp-content/elalicenses.txt                |
// +----------------------------------------------------------------------+
*/

/* ***********************************
 * dirty little debug function
 * ***********************************/	
function logthis($message,$function = __FUNCTION__ ,$line = __LINE__, $file = __FILE__, $info = false) {
	global $debug;
	
	if ($debug) {
		$handle = @fopen(ABSPATH ."wp-content/log.log", 'a');
		if( $handle === false ) {
			return false;
		}
		$now = current_time('mysql', 1);
		$messageHeader = $now." - In ". basename($file) . " - In ".$function." at ".$line ." : \r\n";
		fwrite($handle, $messageHeader);
        if (is_array($message) || is_object($message)){
            fwrite($handle, var_export($message,true));
        }else{
            fwrite($handle, $message);
        }
		fwrite($handle, "\r\n\r\n");
		fclose($handle);
	} else if($info) {
		$handle = @fopen(ABSPATH."wp-content/log.log", 'a');
		if( $handle === false ) {
			return false;
		}
		$now = current_time('mysql', 1);
		$messageHeader = $now." - In ". basename($file) . " - In ".$function." at ".$line ." : \r\n";
		fwrite($handle, $messageHeader);
		fwrite($handle, str_replace("\t", "", serialize($message)));
		fwrite($handle, "\r\n\r\n");
		fclose($handle);
	}
}

function dlog($function = __FUNCTION__, $line = __LINE__){
    global $debug;
    if ($debug) {
		$handle = @fopen(ABSPATH ."wp-content/ela-debug.log", 'a');
		if( $handle === false ) {
			return false;
		}
		$now = current_time('mysql', 1);
		$messageHeader = '['.$now."][Func:".$function.":".$line ."] : \r\n";
		fwrite($handle, $messageHeader);
        $messages = func_get_args();
        $num = func_num_args();
        for ($idx = 2; $idx < $num; $idx ++){
            $message = $messages[$idx];
            if (is_array($message) || is_object($message)){
                fwrite($handle, var_export($message,true));
            }else{
                fwrite($handle, $message);
            }
            fwrite($handle, "\r\n");
        }
		fwrite($handle, "\r\n");
		fclose($handle);
	}
}

class Better_ELA_Cache_Builder {
    /**
     * Cache File
     * @var object
     * @access private
     * @since 0.8
     */
    var $cache;

    /**
     * Exclude Posts IDs
     * @var array
     * @access private
     * @since 0.8
     */
    var $excluded_posts = array();

    /**
     * How Many Posts In Each Year
     * @var array
     * @access private
     * @since 0.8
     */
    var $year_table = array();

    /**
     * How Many Posts In Each Month
     * @var array
     * @access private
     * @since 0.8
     */
    var $month_table = array();

	var $catsTable = array();
	var $postsInCatsTable = array();
	var $postToGenerate = array();
	var $tagsTable = array();
	var $postsInTagsTable = array();
    
	/**
     * Constructor
     */
    function Better_ELA_Cache_Builder() {
        $this->cache = new af_ela_classCacheFile('');
    }

    /**
     * Find out posts you don't want them to be shown in the archives
     * @param string $exclude
     */
    function find_exclude_posts($args) {
        global $wpdb;
        
        $show_page = $args['show_page'] == 1;
        $page_ids = array();
        if (!$show_page){
            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'";
            dlog(__FUNCTION__,__LINE__,'SQL Query: ',$sql);
            $results = $wpdb->get_results($sql);
            foreach ($results as $page){
                $page_ids[] = $page->ID;
            }
        }
        $this->excluded_posts = $page_ids;

        $exclude = trim($args['excluded_categories'], ', ');
        if (empty($exclude)) return;
        $exclude_ids = preg_split('/[\s,]+/',$exclude);
        $exclusion = '(' . implode(',', $exclude_ids) . ')';
        $sql = 'SELECT DISTINCT p.ID '
              ."FROM {$wpdb->posts} p "
              ."INNER JOIN {$wpdb->term_relationships} tr ON ( p.ID=tr.object_id ) "
              ."INNER JOIN {$wpdb->term_taxonomy} tt ON ( tr.term_taxonomy_id=tt.term_taxonomy_id ) "
              .'WHERE tt.taxonomy=\'category\' '
              ."AND tt.term_id IN {$exclusion}";
        dlog(__FUNCTION__,__LINE__,'SQL Query: ',$sql);
        $results = $wpdb->get_results($sql);
        dlog(__FUNCTION__,__LINE__,'Posts In Excluded Categories: ',$results);
        $exclude_ids = array();
        foreach ($results as $post){
            $exclude_ids[] = $post->ID;
        }
        $this->excluded_posts = array_merge($this->excluded_posts, $exclude_ids);
        $this->excluded_posts = array_unique($this->excluded_posts);
        dlog(__FUNCTION__,__LINE__,'Posts to Exclude: ',$this->excluded_posts);
    }
	/* ***********************************
	 * Helper Function : Find info about 
	 * 		updated post.
	 * ***********************************/	
	function buildPostToGenerateTable($exclude, $id, $commentId = false) {
        global $wpdb;

        if (!empty($this->excluded_posts)) {
            $exclusions = ' AND ID NOT IN(' . implode(',', $this->excluded_posts) . ') ';
        }

		if(!$commentId) {
			if($id) { 
				$dojustid = ' AND ID = ' . intval($id) . ' ' ;
                $dojustid2 = ' AND tr.object_id = ' . intval($id) . ' ' ;
			}


			$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, tt.term_id AS `category_id`
				FROM $wpdb->posts 
				INNER JOIN {$wpdb->term_relationships} AS tr
                              ON (ID = tr.object_id)
                INNER JOIN {$wpdb->term_taxonomy} AS tt
                              ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                WHERE tt.taxonomy = 'category'
				AND post_date > 0
				$dojustid $exclusions
                GROUP BY tr.object_id
				ORDER By post_date DESC";
			$results = $wpdb->get_results($query);
            logthis('SQL Query:' . "Result Count:" . count($results) .$query, __FUNCTION__, __LINE__);
			if ($results) {
				foreach($results as $result) {
					$this->postToGenerate['category_id'][] = $result->category_id;
				}
                $this->postToGenerate['new_year']= $results[0]->year;
                $this->postToGenerate['new_month']= $results[0]->month;
            }else{
                return true;
            }
			

            $query = "SELECT t.term_id AS `tag_id`
                      FROM $wpdb->terms AS t
                      INNER JOIN $wpdb->term_taxonomy AS tt
                            ON (t.term_id = tt.term_id)
                      INNER JOIN $wpdb->term_relationships AS tr
                            ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                      WHERE tt.taxonomy = 'post_tag'
                      $dojustid2
                    ";
            $results = $wpdb->get_results($query);
            logthis('SQL Query:' . "Result Count:" . count($results).$query, __FUNCTION__, __LINE__);
            if ($results) {
                foreach($results as $result) {
                    $this->postToGenerate['tag_id'][] = $result->tag_id;
                }
            }
			
			return true;
		} else {
			$query = "SELECT comment_post_ID  
				FROM $wpdb->comments
				WHERE comment_ID = $id AND comment_approved = '1'";
			
			$result = $wpdb->get_var($query);
            logthis('SQL Query:' . "Result Count:" . count($result).$query, __FUNCTION__, __LINE__);
			if ($result) {
				$id = $result;
				if($id) {
					$dojustid = ' AND ID = ' . intval($id) . ' ' ;
				}

				$query = "SELECT YEAR(post_date) AS `year`, 
                                 MONTH(post_date) AS `month`,
                                 tt.term_id AS `category_id`
                          FROM $wpdb->posts
                          INNER JOIN {$wpdb->term_relationships} AS tr
                                     ON (ID = tr.object_id)
                          INNER JOIN {$wpdb->term_taxonomy} AS tt
                                     ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                          WHERE tt.taxonomy = 'category'
                          AND post_date > 0
                          $dojustid $exclusions
                          GROUP BY tr.object_id
                          ORDER By post_date DESC";
				
				$results = $wpdb->get_results($query);
                logthis('SQL Query:' . "Result Count:" . count($results).$query ."\n". var_export($this->postToGenerate,true), __FUNCTION__, __LINE__);
				if($results) {
					foreach($results as $result) {
						$this->postToGenerate['category_id'][]=$result->category_id;
					}
					$this->postToGenerate['post_id'] = $id;
					$this->postToGenerate['new_year']= $results[0]->year;
					$this->postToGenerate['new_month'] = $results[0]->month;
					$this->year_table = array($this->postToGenerate['new_year'] => 0);
					$this->month_table[$this->postToGenerate['new_year']] = array($this->postToGenerate['new_month'] => 0);
					$this->catsTable = $this->postToGenerate['category_id'];
					return true;
				}
			}
			return false;
		}
	}
	/* ***********************************
	 * Helper Function : build Years.
	 * ***********************************/	
    function build_years_table($id = false) {
        global $debug, $wpdb;

        if (!empty($this->excluded_posts)) {
            $exclusions = ' AND `ID` NOT IN(' . implode(',', $this->excluded_posts) . ') ';
        }

		$sql = 'SELECT YEAR(`post_date`) `year`, COUNT(`ID`) `count` '
			    ."FROM {$wpdb->posts} "
                .'WHERE `post_status` = \'publish\' '
                .$exclusions
                .'GROUP BY `year` ORDER By `post_date` DESC';		
		$year_results = $wpdb->get_results($sql);
        dlog(__FUNCTION__, __LINE__, 'SQL Query:'.$sql, 'Results Count:'.count($year_results));

        if ($year_results) {
            foreach ($year_results as $year_result) {
                if ($year_result->count > 0)
                    $this->year_table[$year_result->year] = $year_result->count;
            }
        }
		if (!empty($this->year_table)) {
            if (false !== $id){ //如果更新单篇文章
                $this->cache->readFile('years.dat');
                $diffyears = array_diff_assoc($this->year_table, $this->cache->readFileContent);                
                if (!empty($diffyears)){ //如果Year表发生变化，重写Year表cache
                    $this->cache->contentIs($this->year_table);
                    $this->cache->writeFile('years.dat');
                    dlog(__FUNCTION__,__LINE__,'Years Table Updated:',$this->year_table);
                    $this->year_table = $diffyears;
                    dlog(__FUNCTION__,__LINE__,'Different Years:',$diffyears);
                } else {
                    $this->year_table = array($this->postToGenerate['new_year'] => 0);
                }
            } else {
                $this->cache->contentIs($this->year_table);
                $this->cache->writeFile('years.dat');
                dlog(__FUNCTION__,__LINE__,'Years Table:',$this->year_table);
            }		
		}
	}
	/* ***********************************
	 * Helper Function : build Months.
	 * ***********************************/
	function build_months_table($id = false) {
        global $wpdb;

        if (!empty($this->excluded_posts)) {
            $exclusions = ' AND `ID` NOT IN(' . implode(',', $this->excluded_posts) . ') ';
        }

        foreach ($this->year_table as $year => $y) {
            $sql = 'SELECT MONTH(`post_date`) `month`, COUNT(`ID`) `count` '
				  ."FROM {$wpdb->posts} "
                  ."WHERE YEAR(`post_date`)={$year} "
				  .$exclusions
                  .'AND `post_status`=\'publish\' '
                  .'GROUP BY `month` ORDER By `post_date` DESC';
			$month_results = $wpdb->get_results($sql);
            dlog(__FUNCTION__,__LINE__,'SQL Query: '.$sql,'Results Count: '.count($month_results));

            if (!empty($month_results)) {
                foreach ($month_results as $month_result) {
                    if ($month_result->count > 0){
                        $this->month_table[$year][$month_result->month] = $month_result->count;
                    }
                }
                if (!empty($this->month_table[$year])) {
                    if ($id !== false){
                        $this->cache->readFile($year . '.dat');
                        $diffmonth = array_diff_assoc($this->month_table[$year], $this->cache->readFileContent);
                        if (!empty($diffmonth)){                            
                            $this->cache->contentIs($this->month_table[$year]);
                            $this->cache->writeFile($year . '.dat');
                            dlog(__FUNCTION__,__LINE__,'Year: ',$year,' Month Table Updated: ',$this->month_table[$year]);
                            $this->month_table[$year] = $diffmonth;
                            dlog(__FUNCTION__,__LINE__,'Different Months: ',$this->month_table[$year]);
                        }else{
                            $this->month_table[$year] = array($this->postToGenerate['new_month'] => 0);
                        }
                    } else {
                        $this->cache->contentIs($this->month_table[$year]);
                        $this->cache->writeFile($year . '.dat');
                        dlog(__FUNCTION__,__LINE__,'Year: ',$year,' Month Table: ',$this->month_table[$year]);
                    }                   
                }
            }
        }
    }
	/* ***********************************
	 * Helper Function : build Posts in 
	 * 			Month.
	 * ***********************************/
	function build_posts_in_months_table() {
        global $wpdb;

        if (!empty($this->excluded_posts)) {
            $exclusions = ' AND ID NOT IN(' . implode(',', $this->excluded_posts) . ') ';
        }

        $posts = array();
		$now = current_time('mysql', 1);
        if (empty($this->year_table)) { //TODO: 这里很奇怪，按理说，这里永远不会出现空的情况
            return;
        }
        
		foreach( $this->year_table as $year => $y ) {
			$posts[$year] = array();
            dlog(__FUNCTION__,__LINE__,'Now Processing Year: ',$year);
			foreach( $this->month_table[$year] as $month =>$m ) {
				$posts[$year][$month] = array();
                dlog(__FUNCTION__,__LINE__,'Now Processing Month: ',$month);
                $sql = 'SELECT `ID`,`post_title`,DAYOFMONTH(`post_date`) `day`,`comment_status`,`comment_count` '
                      ."FROM {$wpdb->posts} WHERE YEAR(`post_date`)={$year} "
					  ."AND MONTH(`post_date`)={$month} "
                      .'AND post_status=\'publish\' '
                      .$exclusions
					  .'ORDER By `post_date` DESC';
				$post_results = $wpdb->get_results($sql);
                dlog(__FUNCTION__,__LINE__,'SQL Query: ',$sql,"\nResult Count: ",count($post_results));
				if( $post_results ) {
					foreach( $post_results as $post_result ) {
							$posts[$year][$month][$post_result->ID] = array(
                                $post_result->day,
                                $post_result->post_title,
                                get_permalink($post_result->ID),
                                $post_result->comment_count,
                                $post_result->comment_status
                            );
					}
				}
				if (!empty($posts[$year][$month])) {
					$this->cache->contentIs($posts[$year][$month]);
					$this->cache->writeFile($year . '-' . $month . '.dat');
                    dlog(__FUNCTION__,__LINE__,$year . '-' . $month . '.dat Updated. Content is: ',$posts[$year][$month]);
				}
			}
		}
	}
	/* ***********************************
	 * Helper Function : build Categories.
	 * ***********************************/	
	function buildCatsTable($exclude='', $id = false) {
		$this->buildCatsList('ID', 'asc', FALSE, TRUE, '0', 0, $exclude, TRUE);
        dlog(__FUNCTION__,__LINE__,"Category Table: ",$this->catsTable);
		foreach( $this->catsTable as $category ) {
			$parentcount = 0;
			if(($parentkey = $category[4])) {
				$parentcount++;
				while($parentkey) {
					$parentcount++;
					$this->catsTable[$parentkey][6] = TRUE;
					$parentkey=$this->catsTable[$parentkey][4];
				}
			}
			$this->catsTable[$category[0]][5] = $parentcount;
		}
		foreach( $this->catsTable as $category ) {
			if ($category[6] == TRUE || intval($category[3]) > 0) {
				$this->catsTable[$category[0]][6] = TRUE;
			} else {
				$this->catsTable[$category[0]][6] = FALSE;
			}
		}
		if($id) {
			if ($this->cache->readFile('categories.dat')) {
				$diffTempo = array_diff_assoc($this->cache->readFileContent, $this->catsTable);
				if(!empty($diffTempo)) $diffcats = $diffTempo;
			}
		}
		$this->cache->contentIs($this->catsTable);
        logthis(var_export($this->catsTable,true), __FUNCTION__, __LINE__);
		$this->cache->writeFile('categories.dat');
		if($id) {			
			if (!empty($diffcats)) {
				$this->catsTable = $diffcats;
			} else {
				$this->catsTable = $this->postToGenerate['category_id'];
			}
		}
	}
	/* ***********************************
	 * Helper Function : build list of cats
	 * ***********************************/	
	function buildCatsList($sort_column = 'ID', $sort_order = 'asc', $hide_empty = FALSE, $children=TRUE, $child_of=0, $categories=0, $exclude = '', $hierarchical=TRUE, $id = false) {
		global $wpdb, $category_posts;
		
		if (!empty($exclude)) {
			$excats = preg_split('/[\s,]+/',$exclude);
			if (count($excats)) {
				foreach ($excats as $excat) {
					$exclusions .= ' AND t.term_id <> ' . intval($excat) . ' ';
				}
			}
		}

		if (intval($categories)==0){
			$sort_column = 'cat_'.$sort_column;
            if ($sort_column == 'ID'){
                $sort_column = 't.term_id';
            }else{
                $sort_column = 't.name';
            }
            $query = "SELECT t.term_id AS `cat_ID`, t.name AS `cat_name`, t.slug AS `category_nicename`, tt.parent AS `category_parent`
                      FROM $wpdb->terms AS t
                      INNER JOIN {$wpdb->term_taxonomy} AS tt
                            ON (t.term_id = tt.term_id)
                      WHERE tt.taxonomy = 'category'
                      AND t.term_id > 0
                      $exclusions
                      ORDER BY $sort_column $sort_order";
			
			$categories = $wpdb->get_results($query);
            logthis('SQL Query:' . " Categories: ".count($categories) . $query, __FUNCTION__, __LINE__);
		}

		if (!count($category_posts)) {
			$now = current_time('mysql', 1);	

            $query = "SELECT `term_id` AS `cat_ID`, `count` AS `cat_count`
                      FROM {$wpdb->term_taxonomy} AS t
                      WHERE `taxonomy` = 'category'
                      $exclusions";

			
			$cat_counts = $wpdb->get_results($query);
            logthis('SQL Query:' . " Categories Counts: " . count($cat_counts) .$query, __FUNCTION__, __LINE__);
	        if (! empty($cat_counts)) {
	            foreach ($cat_counts as $cat_count) {
	                if (1 != intval($hide_empty) || $cat_count > 0) {
	                    $category_posts[$cat_count->cat_ID] = $cat_count->cat_count;
	                }
	            }
	        }
		}
		foreach ($categories as $category) {
			if ((intval($hide_empty) == 0 || isset($category_posts[$category->cat_ID])) && (!$hierarchical || $category->category_parent == $child_of) ) {
				$this->catsTable[$category->cat_ID] = array(	$category->cat_ID, 
	 															$category->cat_name,
	 															$category->category_nicename, 
																$category_posts["$category->cat_ID"], 
	 															$category->category_parent);
				if ($hierarchical && $children) {
					$this->buildCatsList(	$sort_column,
										$sort_order, 
										$hide_empty, 
										$children, 
										$category->cat_ID, 
										$categories, 
										$exclude, 
										$hierarchical);
				}
			}
		}
	}
	/* ***********************************
	 * Helper Function : build Posts In 
	 * 			Categories
	 * ***********************************/	
	function buildPostsInCatsTable() {
		global $wpdb, $category_posts;

		if (!empty($this->excluded_posts)) {
            $exclusions = ' AND p.ID NOT IN(' . implode(',', $this->excluded_posts) . ') ';
		}
		$now = current_time('mysql', 1);
		logthis($this->catsTable);
        //TODO 这里foreach也可能遍历空对象，调查原因
        if (empty($this->catsTable)) return;
		foreach( $this->catsTable as $category ) {
			$posts_in_cat[$category[0]] = array();
            $query = "SELECT p.ID AS `post_id`
                      FROM $wpdb->posts AS p
                        INNER JOIN {$wpdb->term_relationships} AS tr
                                   ON (p.ID = tr.object_id)
                        INNER JOIN {$wpdb->term_taxonomy} AS tt
                                   ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                        WHERE tt.taxonomy = 'category'
                        AND tt.term_id = $category[0]
                        $exclusions
            ";
			
			$posts_in_cat_results = $wpdb->get_results($query);
            logthis('SQL Query:' . "Posts in Cat:" . count($posts_in_cat_results) .$query, __FUNCTION__, __LINE__);
			if( $posts_in_cat_results ) {
				$posts_in_cat_results = array_reverse($posts_in_cat_results);
				$post_id_set = array();
                foreach( $posts_in_cat_results as $post_in_cat_result ) {
					$post_id_set[] = $post_in_cat_result->post_id;
				}
                $post_id_set = '(' . implode(',', $post_id_set) . ')';

                $query = "SELECT ID, post_title, post_date as `day`, comment_status, comment_count
                    FROM $wpdb->posts
                    WHERE ID IN $post_id_set
                    AND post_status = 'publish'
                    AND post_date_gmt <= '$now'
                    ORDER By post_date";

                $post_results = $wpdb->get_results($query);
                logthis('SQL Query:' . "Post Results". count($post_results) .$query, __FUNCTION__, __LINE__);
                if( $post_results ) {
                    foreach( $post_results as $post_result ) {
                        $this->postsInCatsTable[$category[0]][$post_result->ID] = array($post_result->day, $post_result->post_title, get_permalink($post_result->ID), $post_result->comment_count, $post_result->comment_status);
                    }
                }

				if ($this->postsInCatsTable[$category[0]]) {
                    logthis(var_export($this->postsInCatsTable[$category[0]],true), __FUNCTION__, __LINE__);
					$this->cache->contentIs($this->postsInCatsTable[$category[0]]);
					$this->cache->writeFile('cat-' . $category[0] . '.dat');
				}
			}
		}
	}
	/* ***********************************
	 * Helper Function : build Tags.
	 * ***********************************/	
	function build_tags_table($id = false, $order = false, $orderparam = 0) {
		
			global $wpdb;
					
			switch($order) {
				case 2: // X is the min number of post per tag
				$ordering = "HAVING tt.count >= ". $orderparam . " ORDER BY tt.count DESC";
				break;
				case 1: // X is the number of tag to show
				$ordering = "ORDER BY tt.count DESC LIMIT ". $orderparam;
				break;
				case 0:
				default:
				$ordering = "";
				break;
			}

            $query = "SELECT t.term_id AS `tag_id`, t.name AS `tag`, tt.count AS tag_count
                      FROM $wpdb->terms AS t
                      INNER JOIN $wpdb->term_taxonomy AS tt
                            ON (t.term_id = tt.term_id)
                      WHERE tt.taxonomy = 'post_tag'
                      $ordering
                      ";

			$tagsSet = $wpdb->get_results($query);			
            dlog(__FUNCTION__,__LINE__,'SQL Query: ',$query,'Result Count: ',count($tagsSet));
			$tagged_posts = 0;
			$posted_tags = 0;
			if( !empty($tagsSet) ) {
				foreach($tagsSet as $tag) {
					if ($tag->tag_count) {
						$this->tagsTable[$tag->tag_id] = array($tag->tag_id, $tag->tag, $tag->tag_count );
						$tagged_posts++;
						if (intval($posted_tags) < intval($tag->tag_count)) $posted_tags = $tag->tag_count;
					}
				}
				if ($order!= false ) {
					$this->tagsTable = $this->arraySort($this->tagsTable, 1);
				}
				
				$this->tagsTable[0] = array($tagged_posts, $posted_tags);
				
				$this->cache->contentIs($this->tagsTable);

                dlog(__FUNCTION__,__LINE__,'Tags Table: ',$this->tagsTable);
				$this->cache->writeFile('tags.dat');
				
				if($id) {
					$this->cache->readFile('tags.dat');
					$difftags = array_diff_assoc($this->cache->readFileContent, $this->tagsTable);
					if (!empty($difftags)) {
						$this->tagsTable = $difftags;
					} else {
						$this->tagsTable = $this->postToGenerate['tag_id'];
					}
				}
			}
		
		if (empty($this->tagsTable)) return false;
		return true;
	}
	/* ***********************************
	 * Helper Function : build Posts In 
	 * 			Tags
	 * ***********************************/	
	function buildPostsInTagsTable() {
		
			global $wpdb;

            if (!empty($this->excluded_posts)) {
                $exclusions = ' AND ID NOT IN(' . implode(',', $this->excluded_posts) . ') ';
            }
			
			foreach( $this->tagsTable as $key => $tag) {
                if($key==0) continue;
                $query = "SELECT ID, post_title, post_date AS `day`, comment_status, comment_count
                          FROM $wpdb->posts
                          INNER JOIN $wpdb->term_relationships AS tr
                                ON (ID = tr.object_id)
                          INNER JOIN $wpdb->term_taxonomy AS tt
                                ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                          WHERE tt.term_id = $tag[0]
                          AND tt.taxonomy = 'post_tag'
                          AND post_status = 'publish'
                          $exclusions
                          ORDER BY post_date";

				$posts_in_tag_results = $wpdb->get_results($query);
                dlog(__FUNCTION__,__LINE__,'SQL Query: ',$query,'Result Count: ',count($posts_in_tag_results));
				if( $posts_in_tag_results ) {

					foreach( $posts_in_tag_results as $post_result ) {
						$this->postsInTagsTable[$tag[0]][$post_result->ID] = array($post_result->day, $post_result->post_title, get_permalink($post_result->ID), $post_result->comment_count, $post_result->comment_status);
					}
					if ($this->postsInTagsTable[$tag[0]]) {
						$this->cache->contentIs($this->postsInTagsTable[$tag[0]]);
						$this->cache->writeFile('tag-' . $tag[0] . '.dat');
					}
				}else{
                    unset($this->tagsTable[$key]);
                    $this->tagsTable[0][0] = $this->tagsTable[0][0] - 1;
                }
			}
            $this->cache->contentIs($this->tagsTable);
            dlog(__FUNCTION__,__LINE__,'Tags Table Rewrite: ',$this->tagsTable);
            $this->cache->writeFile('tags.dat');
		
	}
	/* ***********************************
	 * Helper Function : sort a mulitdim 
	 *          array
	 * ***********************************/		
	function arraySort($array, $key) {
		foreach ($array as $i => $k) {
			$sort_values[$i] = $array[$i][$key];
		}
		asort($sort_values);
		reset($sort_values);
		$i=1;
		while (list ($arr_key, $arr_val) = each ($sort_values)) {
			$sorted_arr[$i++] = $array[$arr_key];
		}
		return $sorted_arr;
	}
}

/* ***********************************
* Cache File Handling class
* ***********************************/	
class af_ela_classCacheFile {
	var $fileContent = array();
	var $readFileContent = array();
	var $fileName;
	var $dbResults = array();
	/* ***********************************
	 * Helper Function : class creator
	 * ***********************************/	
	function af_ela_classCacheFile($filename = false) {
		if($filename===false) {
			$this->fileName = "dummy.dat";
		} else {
			$this->fileName = $filename;
		}
		return true;
	}
	/* ***********************************
	 * Helper Function : set fileContent 
	 * 			property
	 * ***********************************/	
	function contentIs($content) {
		$this->fileContent = $content;
		return true;
	}
	/* ***********************************
	 * Helper Function : read an existing 
	 * 			file and set 
	 * 			readFileContent property
	 * ***********************************/	
	function readFile($filename = false) {
		global $ela_cache_root;
		
		if(!($filename===false)) $this->fileName = $filename;
		
		$handle = @fopen ($ela_cache_root.$this->fileName, "r");
		if( $handle === false ) {
			return false;
		}
		
		$buf = fread($handle, filesize($ela_cache_root.$this->fileName));
		$this->readFileContent = unserialize($buf);
		
		fclose ($handle);
		return true;
	}
	/* ***********************************
	 * Helper Function : actual flushing 
	 * 			of fileContent to the file 
	 * 			system
	 * ***********************************/	
	function writeFile($filename = false) {
		global $ela_cache_root;
		
		if(!($filename===false)) $this->fileName = $filename;
		
		$handle = fopen($ela_cache_root . $this->fileName, 'w');
		if( $handle === false ) {
			return false;
		}
		fwrite($handle, serialize($this->fileContent));
		fclose($handle);
		return true;
	}
	/* ***********************************
	 * Helper Function : deletes cache 
	 * 			files
	 * ***********************************/	
	function deleteFile() {
		global $wpdb, $ela_cache_root;
		$del_cache_path = $ela_cache_root . "*.dat";
		if ( ($filelist=glob($del_cache_path)) === false ) return false;
		foreach ($filelist as $filename) {
			if (!@unlink($filename)) return false;	// delete it
		}
		return true;
	}
}
 
 
?>
