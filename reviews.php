<?php
/**
 * SMM APEX PLUS — Reviews (design preview).
 *
 * Design-only page: the review form layout and the reviews list
 * layout are shown here. Review saving, screenshot upload handling
 * and review display from the database are backend work for the
 * panel owner, so this page shows no invented reviews and the
 * submit button is disabled until that backend is set up.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$config = get_effective_config();
$page_title = 'Reviews';
$meta_description = 'Share your experience with SMM APEX PLUS — tell us which service you used and how it worked for you.';
$active = 'reviews';
require __DIR__ . '/includes/header.php';
?>
<h1>Reviews</h1>
<p>Share your experience with SMM APEX PLUS. Your review helps other customers choose the right service.</p>
<div class="card">
  <h2>Submit a Review</h2>
  <form method="post" action="reviews.php" autocomplete="off">
    <label for="review_name">Full Name</label>
    <input type="text" id="review_name" name="full_name" placeholder="Your full name" disabled>
    <label for="review_service">Service Name</label>
    <input type="text" id="review_service" name="service_name" placeholder="The service you used" disabled>
    <p>Star Rating (1-5)</p>
    <p><label><input type="radio" name="rating" value="1" disabled> 1</label> <label><input type="radio" name="rating" value="2" disabled> 2</label> <label><input type="radio" name="rating" value="3" disabled> 3</label> <label><input type="radio" name="rating" value="4" disabled> 4</label> <label><input type="radio" name="rating" value="5" disabled> 5</label></p>
    <label for="review_description">Review Description</label>
    <textarea id="review_description" name="description" rows="4" placeholder="How was your experience?" disabled></textarea>
    <label for="review_screenshot">Screenshot (optional)</label>
    <input type="file" id="review_screenshot" name="screenshot" accept="image/*" disabled>
    <p class="small">Screenshot is optional. You can submit a review without one.</p>
    <p><button class="btn btn-blue" type="submit" disabled>Submit Review</button></p>
  </form>
</div>
<div class="card"><h3>Average Rating</h3><p class="stat-value">—</p><p class="small">No approved reviews yet.</p></div>
<h2>Customer Reviews</h2>
<p>No reviews yet — reviews submitted by customers will appear here.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
