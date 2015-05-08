# press_canvas
Students with WordPress sites can submit their blog Posts directly to the Canvas LMS to fulfill a course assignment.

Note: The plugin works, but is by no means clean or refined at this point. Please consider it *beta*.

##Background
Canvas is a learning management system (LMS) that makes it easy for teachers to share course content, schedule assignments, and interact with students. In a world of connected, social media, many people are developing their own digital identity in their own space on the open web, outside of the LMS -- using services like WordPress. Students especially are defining who they are, and sharing their work with others, often as digital portfolios that they expect to carry into their post-schooling careers.

How can we encourage students to create in their own, digital spaces (e.g. WordPress) in a way that seamlessly connects to the more formal, teacher-owned course frameworks (e.g. a learning management system like Canvas)? 

##Overview of the Plugin
The plugin adds a widget to the WordPress blog post editing UI that lets authors choose to submit the URL of the blog post to a particular Canvas course assignment as the click save or publish. 

This plugin is added to a student's WordPress environment and connects directly to the Canvas LMS via Canvas's open API. See http://api.instructure.com

The plugin itself uses the following WordPress functions:
* update_option() to store the student's Canvas domain and access token
* wp_remote_request() to get from the Canvas API
* wp_enqueue_script() to perform AJAX-based Canvas API calls
* wp_remote_post() to post to the Canvas Assignments API

The plugin uses the prefixes cnvs_ and prcnvs_ for it's own functions and variables.

##Requirements:
* Students will need to have admin access to their WordPress site in order to install the plugin
* Students will need to generate their Canvas access token from their Canvas Profile
* Teachers will need to be using Canvas Assignments that accept URL submission
