<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<ol>
<li>Upload the plugin files to <code>/wp-content/plugins/contactin-pro</code></li>
<li>Activate the plugin through the Plugins menu in WordPress</li>
<li>Click "Get Started" from the plugin action links for a quick walkthrough</li>
<li>Add the shortcode <code>[contactin_form]</code> to any page or post</li>
<li>Configure your settings under ContactIn → Settings</li>
</ol>

<h3>Quick Start</h3>

<p><strong>Basic Contact Form:</strong></p>
<pre><code>[contactin_form]</code></pre>

<p><strong>Custom Form with Title:</strong></p>
<pre><code>[contactin_form title="Get in Touch"]</code></pre>

<p><strong>Form with Subject Field Disabled:</strong></p>
<pre><code>[contactin_form show_subject="false"]</code></pre>

<h3>Requirements</h3>
<ul>
<li>WordPress <?php echo esc_html( $requires ); ?> or higher (tested up to <?php echo esc_html( $tested ); ?>)</li>
<li>PHP <?php echo esc_html( $requires_php ); ?> or higher</li>
<li>MySQL 5.7 or higher / MariaDB 10.2 or higher</li>
<li>SSL certificate recommended for reCAPTCHA</li>
</ul>
