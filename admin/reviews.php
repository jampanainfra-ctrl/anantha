<?php
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['google_action'])){
    try{
        require_referral_csrf();
        if($_POST['google_action']==='save'){
            save_google_reviews_settings($_POST);
            set_flash('ok','Google Maps connection saved. Select Test connection to check the reviews.');
        }elseif($_POST['google_action']==='test'){
            if(!google_reviews_configured())throw new RuntimeException('Add your Google API key and Place ID first.');
            $result=google_reviews_data();
            if($result['error']!=='')throw new RuntimeException($result['error']);
            set_flash('ok','Google Maps connected. '.count($result['reviews']).' review excerpts are available for display.');
        }else throw new RuntimeException('Unknown Google connection action.');
    }catch(Throwable $e){set_flash('error',$e instanceof RuntimeException ? $e->getMessage() : 'Google connection could not be updated.');}
    redirect('reviews.php');
}

$ready = db_ready();
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'pending', 'published', 'hidden'], true)) {
    $status = 'all';
}

function review_rows(string $search, string $status): array
{
    if (!db_ready()) {
        return [];
    }

    $whereParts = [];
    $params = [];
    if ($status !== 'all') {
        $whereParts[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $whereParts[] = '(client_name LIKE ? OR event_type LIKE ? OR review_text LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = db()->prepare("SELECT * FROM reviews $where ORDER BY created_at DESC, id DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function render_review_rows(array $reviews, string $search, string $status): string
{
    ob_start();
    foreach ($reviews as $review):
        $formId = 'review-form-' . (int) $review['id'];
        $back = 'reviews.php?status=' . rawurlencode($status) . ($search !== '' ? '&q=' . rawurlencode($search) : '');
        ?>
        <tr>
          <td>
            <form id="<?php echo h($formId); ?>" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>"></form>
            <input form="<?php echo h($formId); ?>" type="hidden" name="action" value="save_review">
            <input form="<?php echo h($formId); ?>" type="hidden" name="back" value="<?php echo h(app_url($back, 'admin')); ?>">
            <input form="<?php echo h($formId); ?>" type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
            <input form="<?php echo h($formId); ?>" name="client_name" required value="<?php echo h($review['client_name']); ?>" placeholder="Client name">
          </td>
          <td><input form="<?php echo h($formId); ?>" name="event_type" value="<?php echo h($review['event_type']); ?>" placeholder="Wedding"></td>
          <td><input form="<?php echo h($formId); ?>" type="number" min="1" max="5" name="rating" value="<?php echo h((string) (int) $review['rating']); ?>"></td>
          <td><textarea form="<?php echo h($formId); ?>" name="review_text" rows="3" required><?php echo h($review['review_text']); ?></textarea></td>
          <td><select form="<?php echo h($formId); ?>" name="status"><option value="pending" <?php echo $review['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="published" <?php echo $review['status'] === 'published' ? 'selected' : ''; ?>>Published</option><option value="hidden" <?php echo $review['status'] === 'hidden' ? 'selected' : ''; ?>>Hidden</option></select></td>
          <td><button form="<?php echo h($formId); ?>" class="btn gold">Update</button></td>
          <td>
            <form method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>" onsubmit="return confirm('Delete this review?');">
              <input type="hidden" name="action" value="delete_review">
              <input type="hidden" name="back" value="<?php echo h(app_url($back, 'admin')); ?>">
              <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
              <button class="btn red">Delete</button>
            </form>
          </td>
        </tr>
    <?php endforeach;
    if (!$reviews): ?>
      <tr><td colspan="7">No reviews found. Add Review is at the top of this page.</td></tr>
    <?php endif;
    return admin_csrf_forms(ob_get_clean());
}

$reviews = $ready ? review_rows($search, $status) : [];

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_review_rows($reviews, $search, $status),
        'count' => count($reviews),
    ]);
    exit;
}

admin_page_start('Reviews', 'reviews');
?>
<div class="top">
  <div><h1>Reviews</h1><p>Add, search, edit, update, publish, hide or delete website reviews.</p></div>
</div>
<?php show_flash(); ?>
<?php if (!$ready): ?><div class="msg err">Database is not ready. Import database.sql first.</div><?php endif; ?>
<details class="card add-panel" id="google-connection"<?php echo !google_reviews_configured() ? ' open' : ''; ?>>
  <summary>Google Maps connection &middot; <?php echo google_reviews_configured() ? 'Key saved' : 'Setup needed'; ?></summary>
  <form class="form-grid" method="POST">
    <input type="hidden" name="google_action" value="save">
    <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
    <p class="full">Public reviews come from Google Maps. Places API (New) returns up to five reviews, even when Maps shows a higher total. Fetching every review requires a separate owner-authorized Google Business Profile connection. Website reviews below remain available for internal management.</p>
    <label>Google API key<input type="password" name="api_key" autocomplete="new-password" <?php echo !google_reviews_configured() ? 'required' : ''; ?> placeholder="<?php echo google_reviews_configured() ? 'Leave blank to keep the saved key' : 'Please enter Google API key'; ?>"><span class="hint">The saved key is kept private and is never displayed here.</span></label>
    <label>Google Place ID<input name="place_id" required value="<?php echo h(GOOGLE_PLACE_ID); ?>" placeholder="Please enter Google Place ID"></label>
    <p class="full"><a href="https://developers.google.com/maps/documentation/places/web-service/get-api-key" target="_blank" rel="noopener">Create an API key</a> with Places API (New) enabled and billing linked. Restrict it to Places API (New) and your hosting server's outbound IP address. Use the <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">Place ID finder</a> for Anantha Event Lawns. Google supplies a limited selection of reviews; all reviews remain accessible on Maps.</p>
    <button class="btn gold" type="submit">Save Google connection</button>
  </form>
  <form method="POST" style="padding-top:0">
    <input type="hidden" name="google_action" value="test">
    <input type="hidden" name="csrf_token" value="<?php echo h(referral_csrf_token()); ?>">
    <button class="btn" type="submit">Test connection</button>
  </form>
</details>

<details class="card add-panel package-add-panel" id="add-review">
  <summary>Add Review</summary>
  <form class="form-grid" method="POST" action="<?php echo h(app_url('actions.php', 'admin')); ?>">
    <input type="hidden" name="action" value="save_review">
    <input type="hidden" name="back" value="<?php echo h(app_url('reviews.php', 'admin')); ?>">
    <label>Client<input name="client_name" required placeholder="Client name"></label>
    <label>Event<input name="event_type" placeholder="Wedding"></label>
    <label>Rating<input type="number" min="1" max="5" value="5" name="rating"></label>
    <label>Status<select name="status"><option value="published">Published</option><option value="pending">Pending</option><option value="hidden">Hidden</option></select></label>
    <label class="full">Review<textarea name="review_text" required rows="3" placeholder="Write the review text"></textarea></label>
    <button class="btn gold">Save Review</button>
  </form>
</details>

<section class="card">
  <div class="package-toolbar">
    <form class="package-search" method="GET" action="<?php echo h(app_url('reviews.php', 'admin')); ?>" id="reviewSearch">
      <input type="hidden" name="status" id="reviewStatus" value="<?php echo h($status); ?>">
      <label class="sr-only" for="reviewSearchInput">Search reviews</label>
      <input id="reviewSearchInput" name="q" value="<?php echo h($search); ?>" placeholder="Search reviews">
      <button class="icon-btn search-icon" type="submit" aria-label="Search reviews" title="Search reviews"></button>
      <button class="icon-btn reset-icon" type="button" id="clearReviewSearch" aria-label="Clear search" title="Clear search"></button>
    </form>
    <a class="btn gold" href="#add-review">Add Review</a>
  </div>
  <div class="filters review-filters">
    <?php foreach (['all'=>'All reviews','pending'=>'Pending','published'=>'Published','hidden'=>'Hidden'] as $key => $label): ?>
      <a class="<?php echo $status === $key ? 'on' : ''; ?>" href="?status=<?php echo h($key); ?><?php echo $search !== '' ? '&q=' . h(rawurlencode($search)) : ''; ?>" data-status="<?php echo h($key); ?>"><?php echo h($label); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
    <table class="reviews-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Event</th>
          <th>Rating</th>
          <th>Review</th>
          <th>Status</th>
          <th>Update</th>
          <th>Delete</th>
        </tr>
      </thead>
      <tbody id="reviewTableBody">
        <?php echo render_review_rows($reviews, $search, $status); ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  const reviewSearch = document.getElementById('reviewSearch');
  const reviewSearchInput = document.getElementById('reviewSearchInput');
  const reviewStatus = document.getElementById('reviewStatus');
  const reviewTableBody = document.getElementById('reviewTableBody');
  const clearReviewSearch = document.getElementById('clearReviewSearch');
  const reviewFilters = document.querySelectorAll('.review-filters a');

  const loadReviews = async () => {
    const params = new URLSearchParams({
      ajax: '1',
      status: reviewStatus.value || 'all',
      q: reviewSearchInput.value || ''
    });
    reviewTableBody.classList.add('is-loading');
    try {
      const response = await fetch('<?php echo app_url('reviews.php', 'admin'); ?>?' + params.toString(), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      if (!response.ok) throw new Error('Search failed');
      const data = await response.json();
      reviewTableBody.innerHTML = data.html;
      const cleanParams = new URLSearchParams({ status: reviewStatus.value || 'all' });
      if (reviewSearchInput.value) cleanParams.set('q', reviewSearchInput.value);
      window.history.replaceState({}, '', '<?php echo app_url('reviews.php', 'admin'); ?>?' + cleanParams.toString());
    } catch (error) {
      reviewSearch.submit();
    } finally {
      reviewTableBody.classList.remove('is-loading');
    }
  };

  reviewSearch.addEventListener('submit', (event) => {
    event.preventDefault();
    loadReviews();
  });
  clearReviewSearch.addEventListener('click', () => {
    reviewSearchInput.value = '';
    loadReviews();
  });
  reviewFilters.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      reviewStatus.value = link.dataset.status || 'all';
      reviewFilters.forEach((item) => item.classList.toggle('on', item === link));
      loadReviews();
    });
  });
</script>

<?php admin_page_end(); ?>
