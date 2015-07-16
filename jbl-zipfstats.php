<?php
/**
 * @package Zipfstats
 */
/*
Plugin Name: Zipfstats 
Plugin URI: http://sp.uconn.edu/~jbl00001/zipfstats.zip
Description: A widget to calculate and display Zipf statistics per post/page
Author: James Luberda
Version: 1.2
Author URI: http://sp.uconn.edu/~jbl00001
License: GPLv2 or later
*/

/* Copyright 2014 James Luberda (email: james.luberda@gmail.com)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

This program relies on jqPlot for graph generation, Copyright (c) 2009-2013 Chris Leonello, used under the GPLv2 license.
*/

// verify plugin is not being called directly
if ( !function_exists( 'add_action' ) ) {
        echo "This is a plugin and only functions when called by WordPress\n";
        exit;
}

//unadvertised shortcode that is largely irrelevant

add_shortcode( 'zipf', 'say_nonsense' );
function say_nonsense() {
	return "argle-bargle argle-bargle argle-bargle";
}

//note that the test filter below affects all posts/pages, even though not included in the zipf calcs; in short, demonstrates how a shortcode added via a filter could show content that would not be incorporated into calculations even with strip shortcodes option enabled
// add_filter( 'the_content', 'say_more' );
// function say_more( $content ) { return $content . "[zipf]"; }


// Begin widget code

add_action( 'widgets_init', 'zipfstats_register_widget' );

function zipfstats_register_widget() {
        register_widget( 'zipfstats_widget' );
}

class zipfstats_widget extends WP_Widget {

        function zipfstats_widget() {
                $widget_ops = array( 'classname' => 'zipfstats_widget_class', 'description' => 'Display Zipf Calculations' );
                parent::__construct( 'zipfstats_widget', 'Zipfstats Widget', $widget_ops );
        }

        function widget( $args, $instance ) {
		if ( ( is_single() || is_page() ) && ( current_user_can( 'manage_options' ) || !$instance[ 'jbl_zipf_adminonly' ] ) ) {
        	        extract( $args );
               		echo $before_widget;
                	echo $before_title . $title . $after_title;
                	show_zipfstats( $instance );
                	echo $after_widget;
		}
        }

	function form( $instance ) {
		$jbl_defaults = array ( 'jbl_zipf_adminonly' => 'on', 'jbl_zipf_shortcodes' => 'on', 'jbl_zipf_show_graph' => 'on', 'jbl_zipf_show_wordlist' => 'on', 'jbl_zipf_expand_wordlist' => 'on', 'jbl_zipf_numwords' => '10' );
		$instance = wp_parse_args( (array) $instance, $jbl_defaults );
		extract( $instance );
		?>
			<p>Show Only to Admin Users: <input name="<?php
			echo $this->get_field_name( 'jbl_zipf_adminonly' );
			?>" type='checkbox' <?php checked( $jbl_zipf_adminonly, 'on' );
			?> /></p><p>Strip Shortcode Content from Analysis: <input name="<?php
                        echo $this->get_field_name( 'jbl_zipf_shortcodes' );
                        ?>" type='checkbox' <?php checked( $jbl_zipf_shortcodes, 'on' );
                        ?> /></p><p>Include Graph: <input name="<?php
                        echo $this->get_field_name( 'jbl_zipf_show_graph' ); 
                        ?>" type='checkbox' <?php checked( $jbl_zipf_show_graph, 'on' );
                        ?> /></p><p>Include Word Frequencies: <input name="<?php
                        echo $this->get_field_name( 'jbl_zipf_show_wordlist' ); 
                        ?>" type='checkbox' <?php checked( $jbl_zipf_show_wordlist, 'on' );
                        ?> /></p><p>Expand Word Frequency Table by Default: <input name="<?php
                        echo $this->get_field_name( 'jbl_zipf_expand_wordlist' ); 
                        ?>" type='checkbox' <?php checked( $jbl_zipf_expand_wordlist, 'on' );
                        
			?> /></p><p>Number of Words to Show: <select name="<?php
                        echo $this->get_field_name( 'jbl_zipf_numwords' );
			?>"><?php for ($a = 0; $a < 26; $a++ ) {
				?>
				<option value="<?php echo $a ?>" <?php selected( $jbl_zipf_numwords, $a ); ?>><?php echo $a ?></option>
				<?php
			}
			?>
			</select>
			</p>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$jbl_zipf_options = array( 'jbl_zipf_adminonly', 'jbl_zipf_shortcodes', 'jbl_zipf_show_graph', 'jbl_zipf_show_wordlist', 'jbl_zipf_expand_wordlist', 'jbl_zipf_numwords' );
		$instance = $old_instance;
		foreach ($jbl_zipf_options as $a) {
			$instance[ $a ] = $new_instance [ $a ];
		}
		
		return $instance;
	}

}

function show_zipfstats( $instance ) {
        global $post;
	//if stripping shortcode content, temporarily remove shortcode filter to avoid processing
	if ( $instance['jbl_zipf_shortcodes'] ) {
		remove_filter( 'the_content', 'do_shortcode', 11 );
	}
	$post_content = html_entity_decode( strip_tags( strip_shortcodes( apply_filters( 'the_content', $post->post_content ) ) ), ENT_QUOTES, 'UTF-8' );
	//restore shortcode filter (if removed) for any future use
	if ( $instance['jbl_zipf_shortcodes'] ) {
		add_filter( 'the_content', 'do_shortcode', 11 );
	}
	$post_title = html_entity_decode( strip_tags( apply_filters( 'the_title', $post->post_title ) ), ENT_QUOTES, 'UTF-8' );
	preg_match_all( "/\b([a-zA-Z]|-|’|ñ)+\b/", $post_content, $word_array );
	foreach ( $word_array[0] as $a ) { $word_hash[strtolower($a)]++; }
	arsort( $word_hash );
	$i = 0;
	$maxtblwords = $instance['jbl_zipf_numwords'];
	$zipfpoints = array();
	$linearplot = array();
	$topnwords = array();
	$most_freq = reset( $word_hash );
	foreach ( $word_hash as $b => $c ) {
		$i++;
		if ($i <= $maxtblwords) {
			array_push ( $topnwords, array ( $i, $b, $c ) );
		}
		array_push ( $zipfpoints, array( log10( $i ), log10( $c ) ) );
		array_push ( $linearplot, array( log10( $i ), log10( ( $most_freq/$i ) ) ) );
	}
	$zipfpoints = json_encode( array( $zipfpoints, $linearplot ) );
	
	//load our jqplot js
	$jqpath = plugins_url( 'includes/jqPlot',  __FILE__ ) . "/";
	$jbl_csspath = plugins_url( 'css', __FILE__ ) . "/";
	$jbl_jqPlot_scripts = array(
		'jquery.jqplot.min.js',
		'plugins/jqplot.canvasAxisLabelRenderer.min.js',
		'plugins/jqplot.canvasTextRenderer.min.js',
		'plugins/jqplot.enhancedLegendRenderer.min.js'
	);
	foreach ( $jbl_jqPlot_scripts as $a ) {
		print '<script language="javascript" type="text/javascript" src="' . $jqpath . $a . '"></script>';
	}

	//now style sheets
	print '<link rel="stylesheet" type="text/css" href="' . $jqpath . 'jquery.jqplot.min.css" />';
	print '<link rel="stylesheet" type="text/css" href="'. $jbl_csspath . 'jbl_zipfplot.css" />';

	//and now output graph if selected
	if ( $instance[ 'jbl_zipf_show_graph' ] ) {
?>

<div id="chartdiv" style="height:200px; width:200px;"></div>
<script language="javascript">

(function($) {

$.jqplot('chartdiv', <?php echo $zipfpoints ?>,
{ title:'Zipf Plot of <?php echo $post_title ?>',
	axes:{
		xaxis:{renderer:$.jqplot.LogAxisRenderer, label:'log 10 rank', labelOptions:{fontSize:'10pt'}},
		yaxis:{renderer:$.jqplot.LogAxisRenderer, label:'log 10 freq', labelRenderer:$.jqplot.CanvasAxisLabelRenderer, tickOptions:{formatString:'%5s'}, labelOptions:{fontSize:'10pt'}}
	},
	series:[ 
		{label:'Actuals'},
		{label:'Zipfian Distribution'}
		
         ],
	legend: { renderer: $.jqplot.EnhancedLegendRenderer, show:true, location: 's', placement: 'outside', marginTop: '60px', border: 'none' },

});

var w = parseInt($(".jqplot-yaxis").width(), 10) + parseInt($("#chart").width(), 10);
var h = parseInt($(".jqplot-title").height(), 10) + parseInt($(".jqplot-xaxis").height(), 10) + parseInt($("#chart").height(), 10);
$("#chart").width(w).height(h);
plot.replot(); 

})(jQuery);

</script>

<?php
	}
	//show wordlist toggle if checked, display table on load if expand is also checked
	if ( $instance[ 'jbl_zipf_show_wordlist' ] ) {
?>

<div id="jbl_showwordtable" style="margin-top: 55px;">Show/Hide Word Frequencies</div>


<table id="jbl_wordtable" style="display: <?php echo ( $instance[ 'jbl_zipf_expand_wordlist' ] == 'on' ) ? '' : 'none' ?>; margin-top: 5px;">
<tr><td>Rank</td><td>Word</td><td>Count</td></tr>

<?php
	foreach ( $topnwords as $a ) {
		list( $rank, $word, $count ) = $a;
		print "<tr><td id='jbl_wordcell'>$rank</td><td id='jbl_wordcell'>$word</td><td id='jbl_wordcell'>$count</td></tr>";
	}
?>
</table>
 
<script language="javascript">
(function($) {
	var flip = <?php echo ( $instance[ 'jbl_zipf_expand_wordlist' ] == 'on' ? 1 : 0 )?>;
	$( "#jbl_showwordtable" ).click(function() {
		$( "#jbl_wordtable" ).toggle( flip++ % 2 === 0 );
	});
})(jQuery);
</script>

<?php
	}
		
}

?>
