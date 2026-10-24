<?php
require_once __DIR__.'/../includes/admin_layout.php';
require_admin();
$ready=db_ready();
if($ready)ensure_package_schema();
$filter=in_array($_GET['filter'] ?? 'all',['all','active','inactive'],true)?($_GET['filter'] ?? 'all'):'all';
$search=trim((string)($_GET['q'] ?? ''));
function package_guest_limits(string $range):array{
    preg_match_all('/\d+/',$range,$matches);$numbers=array_map('intval',$matches[0] ?? []);
    return count($numbers)>1?[$numbers[0],$numbers[1]]:[0,$numbers[0] ?? 100];
}
function package_rows(string $filter,string $search):array{
    if(!db_ready())return [];
    $where=[];$params=[];
    if($filter!=='all'){$where[]='is_active=?';$params[]=$filter==='active'?1:0;}
    if($search!==''){$where[]='(package_name LIKE ? OR guest_range LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
    $stmt=db()->prepare('SELECT * FROM event_packages'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY display_order,id');$stmt->execute($params);return $stmt->fetchAll();
}
function render_package_row(array $package,string $key,bool $new=false):string{
    [$min,$max]=package_guest_limits((string)$package['guest_range']);
    $fields=[['package_name','Package name',$package['package_name'],'text',true],['guest_min','Minimum guests',$min,'number',true],['guest_max','Maximum guests',$max,'number',true],['price','Price',$package['price'],'number',false],['discount_percent','Discount percent',$package['discount_percent'] ?? '0','number',false],['discount_reason','Discount reason',$package['discount_reason'] ?? '','text',false],['display_order','Display order',$package['display_order'],'number',false]];
    ob_start(); ?>
    <tr data-package-key="<?php echo h($key); ?>"<?php echo $new?' data-new="true"':''; ?>>
      <?php foreach($fields as $index=>[$name,$title,$value,$type,$required]):$fieldId='package-'.$key.'-'.$name; ?>
      <td>
        <?php if($index===0): ?><input type="hidden" form="packageBulkForm" name="packages[<?php echo h($key); ?>][version]" value="<?php echo $new?'':h(package_edit_version($package)); ?>"><?php endif; ?>
        <label class="package-cell-label" for="<?php echo h($fieldId); ?>"><?php echo h($title); ?></label>
        <?php if($type==='text'): ?><textarea form="packageBulkForm" id="<?php echo h($fieldId); ?>" data-field="<?php echo h($name); ?>" name="packages[<?php echo h($key); ?>][<?php echo h($name); ?>]" rows="1" maxlength="<?php echo $name==='package_name'?160:255; ?>" <?php echo $required?'required':''; ?>><?php echo h((string)$value); ?></textarea>
        <?php else: ?><input form="packageBulkForm" id="<?php echo h($fieldId); ?>" data-field="<?php echo h($name); ?>" name="packages[<?php echo h($key); ?>][<?php echo h($name); ?>]" type="number" min="0" <?php echo $name==='discount_percent'?'max="100"':''; ?> step="<?php echo in_array($name,['price','discount_percent'],true)?'0.01':'1'; ?>" value="<?php echo h((string)$value); ?>" <?php echo $required?'required':''; ?>><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td><label class="package-active-check"><input form="packageBulkForm" type="checkbox" data-field="is_active" name="packages[<?php echo h($key); ?>][is_active]" <?php echo (int)$package['is_active']===1?'checked':''; ?>>Active</label><?php if($new): ?><span class="package-row-tag">New</span><?php endif; ?></td>
      <td><button class="btn package-remove" type="button" data-package-remove aria-label="Remove package">Remove</button></td>
    </tr>
    <?php return ob_get_clean();
}
function render_package_rows(array $packages,string $filter='',string $search=''):string{
    $html='';foreach($packages as $package)$html.=render_package_row($package,(string)$package['id']);return $html;
}
if(isset($_GET['ajax'])){
    header('Content-Type: application/json');$rows=package_rows($filter,$search);echo json_encode(['html'=>render_package_rows($rows),'count'=>count($rows)]);exit;
}
$packages=$ready?package_rows('all',''):[];
admin_page_start('Packages','packages');
?>
<div class="top"><div><h1>Packages</h1><p>Edit package details in the table and save all changes together.</p></div></div>
<?php show_flash(); ?>
<?php if(!$ready): ?><div class="msg err">The database is not ready.</div><?php endif; ?>
<section class="card package-editor">
  <div class="package-toolbar">
    <form class="package-search" method="GET" id="packageSearch">
      <input type="hidden" name="filter" id="packageFilter" value="<?php echo h($filter); ?>">
      <label class="sr-only" for="packageSearchInput">Search package</label>
      <input id="packageSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Please enter search term">
      <button class="icon-btn search-icon" type="submit" aria-label="Search packages"></button>
      <button class="icon-btn reset-icon" type="button" id="clearPackageSearch" aria-label="Clear search"></button>
    </form>
    <button class="btn gold" type="button" id="addPackage">+ Add package</button>
  </div>
  <div class="filters package-filters" role="group" aria-label="Filter packages">
    <?php foreach(['all'=>'All packages','active'=>'Active','inactive'=>'Inactive'] as $key=>$label): ?><a class="<?php echo $filter===$key?'on':''; ?>" href="?filter=<?php echo h($key); ?>" data-filter="<?php echo h($key); ?>"><?php echo h($label); ?></a><?php endforeach; ?>
  </div>
  <p class="package-table-help">Changes are saved together. Remove marks a row for deletion; Undo restores it before saving.</p>
  <form id="packageBulkForm" method="POST" action="<?php echo h(app_url('actions.php','admin')); ?>">
    <input type="hidden" name="action" value="save_packages_bulk">
    <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
    <input type="hidden" name="back" value="<?php echo h(app_url('packages.php','admin')); ?>">
    <div id="packageEditorError" class="msg err" role="alert" tabindex="-1" hidden></div>
    <div class="table-wrap package-table-scroll" role="region" aria-label="Editable packages" tabindex="0">
      <table class="package-table"><thead><tr><th scope="col">Package <span class="required-mark" aria-hidden="true">*</span></th><th scope="col">Min guests <span class="required-mark" aria-hidden="true">*</span></th><th scope="col">Max guests <span class="required-mark" aria-hidden="true">*</span></th><th scope="col">Price (Rs)</th><th scope="col">Discount %</th><th scope="col">Reason</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col">Remove</th></tr></thead>
      <tbody id="packageTableBody"><?php echo render_package_rows($packages); ?><tr id="packageEmpty"<?php echo $packages?' hidden':''; ?>><td colspan="9">No packages match your search. Clear the filters or add a package.</td></tr></tbody></table>
    </div>
    <input type="hidden" name="submission_complete" value="1">
    <div class="package-save-bar"><span id="packageSaveStatus" role="status" aria-live="polite">No unsaved changes.</span><button class="btn gold" id="savePackages" type="submit" disabled>Save all changes</button></div>
  </form>
  <noscript><p>Please enable JavaScript to edit and save packages together.</p></noscript>
</section>
<template id="newPackageRow"><?php echo render_package_row(['package_name'=>'','guest_range'=>'0 to 100 guests','price'=>'','discount_percent'=>'0','discount_reason'=>'','display_order'=>10,'is_active'=>1],'__KEY__',true); ?></template>
<script src="<?php echo h(app_url('../assets/admin-packages.js','admin')); ?>" defer></script>
<?php admin_page_end(); ?>