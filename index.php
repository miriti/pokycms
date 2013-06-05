<?php
include('.instance.inc.php');

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
function getContent($route, $position=0) 
{
	global $contentManifest;

	$routeParts = explode('/', $route);
	$folder = $routeParts[0];
	$file = $routeParts[1];

	$folderPath = CONTENT_DIR . '/' . $folder;
	if(file_exists($folderPath))
	{
		$content = (object) array('content' => $folder, 'contentList' => array(), 'page' => null, 'file' => null, 'position' => null);

		if($file == 'index') {
			$dir = opendir($folderPath);	
			$num = 0;
			$contentList = array();
			while (FALSE !== ($f = readdir($dir))) {
				if($f[0] != '.'){
					$subContent = getContent($folder . '/' . str_replace(".json", "", $f), $num);

					if(is_numeric($subContent->position)){
						$contentList[intval($subContent->position)] = $subContent;
					}else{
						$contentList[$num] = $subContent;
					}

					$num++;
				}
			}
			ksort($contentList);
			$content->contentList = $contentList;
			return $content;
		}else{
			if(file_exists('data/content/' . $folder . '/' . $file . '.json'))
			{
				$decoded_file = json_decode(file_get_contents($folderPath . '/' . $file . '.json'));
				$content->file = $file;
				if(isset($decoded_file->position)){
					$content->position = $decoded_file->position;
				}else{
					$content->position = $position;
				}
				$content->page = $decoded_file;
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
			$index_file = SNIPPETS_DIR . '/' . $content->content . '_index.php';
			if(file_exists($index_file))
			{
				include($index_file);
			}else{
				foreach ($content->contentList as $contentItem) 
				{
					renderContentPart($contentItem);
				}
			}
		}
	}
}

function admin_bar()
{
	global $lang;

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
				<li><a href="/-admin">Admin</a> (<a href="/-admin?logout=1"><?php echo $lang->logout; ?></a>)</li>				
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
	global $lang;

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
						<h3><?php echo $lang->admin_password; ?></h3>
					</div>
					<div class="modal-body">					
						<input type="password" name="password">					
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary"><?php echo $lang->log_in; ?></button>
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
	global $site, $lang;

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
					<a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $lang->more; ?> <b class="caret"></b></a>
					<ul class="dropdown-menu">
						<li><a href="?filemanager"><?php echo $lang->filemanager; ?></a></li>
						<li><a href="?menueditor"><?php echo $lang->menu_editor; ?></a></li>
						<li><a href="?site_props"><?php echo $lang->site_props; ?></a></li>
						<li class="divider"></li>
						<li><a href="/-admin?logout=1"><?php echo $lang->logout; ?></a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
	<?php
}

function admin_editor_list($editor)
{
	global $lang;

	$currentEditor = admin_get_editor($editor);
	$content = getContent($currentEditor->content . '/index');

	?>
	<h1><?php echo $currentEditor->caption; ?></h1>
	<div class="row">
		<div class="span12">
			<a href="?editor=<?php echo $editor; ?>&action=add" class="btn btn-primary"><i class="icon-plus icon-white"></i> <?php echo $lang->add; ?></a>
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
						<a href="?editor=<?php echo $editor; ?>&action=delete&uid=<?php echo $contentItem->file; ?>" onclick="return confirm('<?php echo $lang->are_you_sure; ?>');" class="btn btn-mini btn-danger"><i class="icon-remove icon-white"></i></a>
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
	global $lang;

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
			return '<input type="' . $type . '" name="' . $group . '[' . $name . ']" class="span6" /><br /><small><b>' . $upload_mb . '</b>Mb '.$lang->max.'!</small>';
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
	global $lang;

	$editor = admin_get_editor($editor_name);
	if($uid === false)
	{
		$uid = md5(time());
	}
	?>
	<form action="" method="post" enctype="multipart/form-data">
		<div class="row">
			<div class="span12"><strong><?php echo $lang->unique_id; ?></strong></div>
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
				<button class="btn btn-primary" type="submit"><?php echo $lang->save; ?></button>
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
		$upload_dir = FILES_DIR . '/' . $editor_name;

		if(file_exists($upload_dir))
		{
			mkdir($upload_dir);
		}

		$upload_file_name = $upload_dir . '/' . $_FILES[$editor_name]['name'][$file_name];
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

function admin_filemanager()
{
	?>
	<div class="row">
		<div class="span12"><iframe src="/filemanager/" frameborder="0" width="100%" height="800"></iframe></div>
	</div>
	<?php
}

function admin_menu_editor()
{
	global $menus, $lang;

	if(isset($_POST['menus']))
	{
		$menus = $_POST['menus'];

		foreach ($menus as $menuName => &$menuItems) {
			foreach ($menuItems as $menuItemId => &$menuItem) {
				if(trim($menuItem['title']) == "")
				{
					unset($menuItems[$menuItemId]);
				}
			}
		}

		$newItem = $_POST['new'];

		foreach ($newItem as $menuName => $newMenuItem) {
			if(trim($newMenuItem['title']) != "")
			{
				$menus[$menuName][] = $newMenuItem;
			}
		}

		file_put_contents('data/menus.json', json_encode($menus));
		admin_redirect("?menueditor");
		return;
	}

	?>
	<form action="" method="post">
	<?php
	
	foreach ($menus as $menuName => $menu) 
	{
	?>
	<h2><?php echo $menuName; ?></h2>	
	<?php
		foreach ($menu as $itemNum => $menuItem) 
		{
			?>
			<div class="row">
				<div class="span3"><input type="text" name="menus[<?php echo $menuName; ?>][<?php echo $itemNum; ?>][title]" value="<?php echo $menuItem->title; ?>"></div>
				<div class="span4"><input type="text" name="menus[<?php echo $menuName; ?>][<?php echo $itemNum; ?>][route]" value="<?php echo $menuItem->route; ?>"></div>
			</div>
			<?php
		}
		?>
		<h4><?php echo $lang->add; ?></h4>
		<div class="row">
			<div class="span3"><input type="text" name="new[<?php echo $menuName; ?>][title]" value=""></div>
			<div class="span4"><input type="text" name="new[<?php echo $menuName; ?>][route]" value=""></div>
		</div>
		<hr>
		<?php
	}
	?>	
	<div class="row">
		<div class="span12"><button type="submit" class="btn btn-primary"><?php echo $lang->save; ?></button></div>
	</div>
	</form>
	<?php
}

function admin_site_props()
{
	global $site, $lang;

	if(isset($_POST['siteProps']))
	{
		$site = $_POST['siteProps'];
		file_put_contents('data/site.json', json_encode($site));
		admin_redirect("?site_props");
		return;
	}	

	?>
	<form action="" method="post">
		<?php foreach ($site as $prop_name => $prop_val) { ?>
		<div class="row">
			<div class="span2"><?php echo $prop_name; ?></div>
			<div class="span2"><input type="text" name="siteProps[<?php echo $prop_name; ?>]" value="<?php echo $prop_val; ?>"></div>
		</div>
		<?php } ?>
		<div class="row">
			<div class="span4"><button type="submit" class="btn btn-primary"><?php echo $lang->save; ?></button></div>
		</div>
	</form>
	<?php
}

/**
 * Admin main
 *
 */
function admin_main()
{		
	if(isset($_GET['logout']))
	{
		unset($_SESSION['logout']);
		session_destroy();
		header("Location: /");
	}
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
			plugins: "image media code",
			language : "ru",
			relative_urls: false
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
				}else{
					if(isset($_GET['filemanager']))
					{
						admin_filemanager();
					}
					if(isset($_GET['menueditor']))
					{
						admin_menu_editor();
					}
					if(isset($_GET['site_props']))
					{
						admin_site_props();
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

$lang = json_decode(file_get_contents('lang/'.CMS_LANGUAGE.'.json'));
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