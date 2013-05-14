<?php

define("CLEAN_URLS", true);
define("DEFAULT_ROUTE", "index");
define("DATA_DIR", "data");
define("CONTENT_DIR", DATA_DIR . "/content");
define("SNIPPETS_DIR", DATA_DIR . "/snippets");
define("FILES_DIR", DATA_DIR . "/files");
define("IN_CMS", true);
define("ADMIN_PASSWORD", "admin123");

session_start();

/**
 *
 */
function buildUrl($route) 
{
	if(CLEAN_URLS){
		return "/" . $route;
	}else{
		return "index.php?r=" . $route;
	}
}

/**
 *
 */
function getContent($route) 
{
	global $contentManifest;

	$routeParts = explode('/', $route);
	$folder = $routeParts[0];
	$file = $routeParts[1];

	$folderPath = CONTENT_DIR . '/' . $folder;
	if(file_exists($folderPath))
	{		
		$content = (object) array('content' => $folder, 'contentList' => array(), 'page' => null, 'file' => null);

		if($file == 'index') {
			$dir = opendir($folderPath);	
			while (FALSE !== ($f = readdir($dir))) {
				if($f[0] != '.'){
					$content->contentList[] = getContent($folder . '/' . str_replace(".json", "", $f));
				}
			}
			return $content;
		}else{
			if(file_exists('data/content/' . $folder . '/' . $file . '.json'))
			{
				$content->file = $file;
				$content->page = json_decode(file_get_contents($folderPath . '/' . $file . '.json'));
				return $content;
			}else{
				return null;
			}
		}
	}else{
		return null;
	} 
}

function renderContentPart($contentItem)
{
	$snippetFile = SNIPPETS_DIR . '/' . $contentItem->content . '.php';
	$file = $contentItem->file;
	$page = $contentItem->page;
	include($snippetFile);
}

/**
 *
 */
function renderContent($content) 
{
	if($content->page)
	{
		renderContentPart($content);
	}else{
		if($content->contentList) 
		{
			foreach ($content->contentList as $contentItem) 
			{
				renderContentPart($contentItem);
			}
		}
	}
}

function admin_bar()
{
	if(isset($_SESSION['admin']))
	{
		?>
		<style>
		#admin_bar { position: fixed; background-color: black; color: white; left: 0; top: 0; right: 0; }
		#admin_bar ul {list-style: none; margin: 0; padding: 4px; }
		#admin_bar ul li { display: inline; }
		#admin_bar ul li a { color: white; text-decoration: underline; }
		#admin_bar ul li a:hover { text-decoration: none; }
		</style>
		<div id="admin_bar">
			<ul>
				<li><a href="/-admin">Admin</a> (<a href="/-admin?logout=1">logout</a>)</li>				
			</ul>
		</div>
		<?php
	}
}

function admin_redirect($url)
{
	?>
	<script type="text/javascript">
	window.location.href = '<?php echo $url; ?>';
	</script>
	<?php
}

/**
 * Admin login
 */
function admin_login()
{
	if(isset($_POST['password']))
	{
		if($_POST['password'] == ADMIN_PASSWORD)
		{
			$_SESSION['admin'] = true;
			admin_redirect(buildUrl('-admin'));
		}
	}
	?>
	<div class="row">
		<div class="span12">
			<form action="" method="post">
				<div class="modal">
					<div class="modal-header">					
						<h3>Admin password</h3>
					</div>
					<div class="modal-body">					
						<input type="password" name="password">					
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary">Log in</button>
					</div>
				</div>
			</form>
		</div>
	</div>
	<?php
}

function admin_get_editors()
{
	return json_decode(file_get_contents(DATA_DIR . '/editors.json'));	
}

function admin_get_editor($editor)
{
	$editors = admin_get_editors();

	foreach ($editors as $e) {
		if($e->content == $editor)
		return $e;
	}
}

function admin_header()
{
	global $site;
	$editors = admin_get_editors();
	$selected_editor = isset($_GET['editor']) ? $_GET['editor'] : '';
	?>
	<div class="navbar navbar-inverse">
		<div class="navbar-inner">
			<a class="brand" href="/"><?php echo $site->siteName; ?></a>
			<ul class="nav">
				<?php 
				foreach ($editors as $editorItem) { ?>
				<li<?php if($editorItem->content == $selected_editor) {?> class="active"<?php } ?>><a href="-admin?editor=<?php echo $editorItem->content; ?>"><?php echo $editorItem->caption; ?></a></li>
				<?php } ?>
			</ul>
			<ul class="nav pull-right">
				<li class="dropdown">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown">More <b class="caret"></b></a>
					<ul class="dropdown-menu">
						<li><a href="#">Upload files</a></li>
						<li><a href="#">Site properties</a></li>
						<li><a href="#">Menu editor</a></li>
						<li class="divider"></li>
						<li><a href="/-admin?logout=1">Logout</a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
	<?php
}

function admin_editor_list($editor)
{
	$currentEditor = admin_get_editor($editor);
	$content = getContent($currentEditor->content . '/index');

	?>
	<h1><?php echo $currentEditor->caption; ?></h1>
	<div class="row">
		<div class="span12">
			<a href="?editor=<?php echo $editor; ?>&action=add" class="btn btn-primary"><i class="icon-plus icon-white"></i> Add</a>
		</div>
		<?php if($content->contentList) { ?>
		<div class="span12">
			<table class="table table-hover">
				<thead>
					<tr>
						<?php foreach ($currentEditor->fields as $editorField) {
							if($editorField->list)
							echo '<th>' . $editorField->text . '</th>';
					} ?>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php 					
				foreach ($content->contentList as $contentItem) {
					echo '<tr>';
					foreach ($currentEditor->fields as $editorField) {
						if($editorField->list)
						echo '<td>'.$contentItem->page->{$editorField->name}.'</td>';
				}
				?>
				<td>
					<div class="btn-group">
						<a href="?editor=<?php echo $editor; ?>&action=edit&uid=<?php echo $contentItem->file; ?>" class="btn btn-mini btn-primary"><i class="icon-edit icon-white"></i></a>
						<a href="?editor=<?php echo $editor; ?>&action=delete&uid=<?php echo $contentItem->file; ?>" onclick="return confirm('Are you sure?');" class="btn btn-mini btn-danger"><i class="icon-remove icon-white"></i></a>
					</div>
				</td>
				<?php
				echo '</tr>';
			}					
			?>
		</tbody>
	</table>
</div>
<?php } ?>
</div>
<?php
}

/**
 *
 */
function admin_generate_input($type, $group, $name, $value='')
{
	if(($type == 'date') && ($value == ''))
	{
		$value = date("Y-m-d");
	}

	switch ($type) {
		case 'html':
			return '<textarea name="' . $group . '[' . $name . ']" class="span6 mce" rows="10" cols="50">'.$value.'</textarea>';		
		case 'longtext':
			return '<textarea name="' . $group . '[' . $name . ']" class="span6" rows="10" cols="50">'.$value.'</textarea>';
		case 'file':			
			$max_upload = (int)(ini_get('upload_max_filesize'));
			$max_post = (int)(ini_get('post_max_size'));
			$memory_limit = (int)(ini_get('memory_limit'));
			$upload_mb = min($max_upload, $max_post, $memory_limit);
			return '<input type="' . $type . '" name="' . $group . '[' . $name . ']" class="span6" /><br /><small><b>' . $upload_mb . '</b>Mb max!</small>';
			break;
		default:
			return '<input type="' . $type . '" name="' . $group . '[' . $name . ']" value="' . $value . '" class="span6" />';
	}
}

/**
 *
 */
function admin_build_form($editor_name, $data = array(), $uid = false)
{
	$editor = admin_get_editor($editor_name);
	if($uid === false)
	{
		$uid = md5(time());
	}
	?>
	<form action="" method="post" enctype="multipart/form-data">
		<div class="row">
			<div class="span12"><strong>Unique identifier</strong></div>
		</div>
		<div class="row">
			<div class="span12"><input type="text" name="<?php echo $editor_name; ?>[uid]" value="<?php echo $uid; ?>" class="span6"></div>
		</div>
		<?php
		foreach ($editor->fields as $editorField) {
			?>
			<div class="row">
				<div class="span12"><strong><?php echo $editorField->text; ?></strong></div>
			</div>
			<div class="row">
				<div class="span12"><?php echo admin_generate_input($editorField->type, $editor_name, $editorField->name, isset($data[$editorField->name]) ? $data[$editorField->name] : ''); ?></div>
			</div>
			<?php
		}
		?>
		<div class="row">
			<div class="span12">
				<button class="btn btn-primary" type="submit">Save</button>
			</div>
		</div>
	</form>
	<?php
}

/**
 * Upload file
 *
 */
function admin_upload_file($editor_name, $file_name)
{
	if(isset($_FILES[$editor_name]['error'][$file_name]) && ($_FILES[$editor_name]['error'][$file_name] == 0))
	{
		$upload_file_name = FILES_DIR . '/' . $_FILES[$editor_name]['name'][$file_name];
		if(move_uploaded_file($_FILES[$editor_name]['tmp_name'][$file_name], $upload_file_name))
		{
			return '/' . $upload_file_name;
		}else{
			return false;
		}
	}
	
	return false;	
}

/**
 *
 */
function admin_save_record($editor_name, $data)
{
	$editor = admin_get_editor($editor_name);

	$uid = isset($data['uid']) ? $data['uid'] : time();

	$jsonData = array();

	foreach ($editor->fields as $editorField) {
		switch ($editorField->type) {
			case 'file':
				$jsonData[$editorField->name] = admin_upload_file($editor_name, $editorField->name);
				break;			
			default:
				$jsonData[$editorField->name] = $data[$editorField->name];	
				break;
		}		
	}

	$newFileName = CONTENT_DIR . '/' . $editor_name . '/' . $uid . '.json';
	file_put_contents($newFileName, json_encode($jsonData));	
}

/**
 * Add content
 *
 */
function admin_editor_add($editor_name) 
{	
	if(isset($_POST[$editor_name]))
	{
		admin_save_record($editor_name, $_POST[$editor_name]);
		admin_redirect(buildUrl('-admin') . '?editor=' . $editor_name);
	}
	
	admin_build_form($editor_name);
}

/**
 * Edit content 
 *
 */
function admin_editor_edit($editor_name, $uid)
{
	if(isset($_POST[$editor_name]))
	{
		admin_save_record($editor_name, $_POST[$editor_name]);
		admin_redirect(buildUrl('-admin') . '?editor=' . $editor_name);
	}

	$content = getContent($editor_name . '/' . $uid);
	admin_build_form($editor_name, (array)$content->page, $uid);
}


/**
 * Delete content
 */
function admin_delete($editor, $uid)
{
	@unlink(CONTENT_DIR . '/' . $editor . '/' . $uid . '.json');
	admin_redirect(buildUrl('-admin') . '?editor=' . $editor);
}

/**
 * Admin main
 *
 */
function admin_main()
{		
	?>
	<!doctype html>
	<html>
	<head>
		<title>Admin</title>
		<link rel="stylesheet" href="/css/bootstrap.min.css">	
		<script type="text/javascript" src="/js/tinymce/tinymce.min.js"></script>
		<script type="text/javascript">
		tinymce.init({
			selector: "textarea.mce",
			plugins: "image media",
			language : "ru"
		});
		</script>		
	</head>
	<body>
		<script src="http://code.jquery.com/jquery.js"></script>
		<script src="/js/bootstrap.min.js"></script>
		<div class="container">
			<?php
			if(isset($_SESSION['admin']))
			{
				admin_header();
				if(isset($_GET['editor'])){
					$editor = $_GET['editor'];

					if(isset($_GET['action']))
					{
						switch ($_GET['action']) {
							case 'add':
								admin_editor_add($editor);
								break;
							case 'edit':
								admin_editor_edit($editor, $_GET['uid']);
								break;
							case 'delete':
								admin_delete($editor, $_GET['uid']);							
								break;							
							default:
								admin_editor_list($editor);
								break;
						}
					}else{
						admin_editor_list($editor);
					}
				}
			}else{
				admin_login();
			}
			?>
		</div>
	</body>
	</html>
	<?php
}

$contentManifest = json_decode(file_get_contents('data/contentManifest.json'));
$site = json_decode(file_get_contents('data/site.json'));

if(isset($_GET['r'])) {
	$route = $_GET['r'];
}else{
	if(isset($site->defaultRoute)) {
		$route = $site->defaultRoute;
	}else{
		$route = DEFAULT_ROUTE;
	}
}

$menus = json_decode(file_get_contents('data/menus.json'));

if($route == '-admin')
{
	admin_main();
}else{
	$content = getContent($route);
	include("layout.php");
}

?>