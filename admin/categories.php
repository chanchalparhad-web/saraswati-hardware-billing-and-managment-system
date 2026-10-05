<?php
$pageTitle = "Category Management";
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin', 'staff']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = get_db();
$user = current_user();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_category') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $msg = 'Category Name cannot be empty!';
            $msgType = 'danger';
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $msg = 'Category updated successfully!';
                $msgType = 'success';
            } else {
                $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $msg = 'Category added successfully!';
                $msgType = 'success';
            }
        }
    } elseif ($action === 'delete_category' && $user['role'] === 'admin') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE categories SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $msg = 'Category deactivated successfully!';
            $msgType = 'warning';
        }
    }
}

// Fetch categories with product counts
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 1 
    WHERE c.status = 1 
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();
?>

<div id="content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show fw-bold" role="alert">
                <?php echo sanitize($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Category Management</h4>
                <p class="text-muted small mb-0">Organize hardware store products by departmental categories</p>
            </div>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetCatForm()">
                <i class="fa-solid fa-plus me-1"></i> ADD CATEGORY
            </button>
        </div>

        <!-- Categories List Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-folder text-warning me-2"></i><?php echo sanitize($cat['name']); ?></h5>
                            <span class="badge bg-primary-soft text-primary fw-bold"><?php echo $cat['product_count']; ?> Products</span>
                        </div>
                        <p class="text-muted small mb-3 flex-grow-1"><?php echo sanitize($cat['description'] ?? 'No description provided.'); ?></p>
                        
                        <div class="border-top pt-2 d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick='editCategory(<?php echo json_encode($cat); ?>)'>
                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                            </button>
                            <?php if ($user['role'] === 'admin'): ?>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Deactivate category?')">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" id="cat_id" value="0">
                
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="catModalTitle"><i class="fa-solid fa-folder-plus me-2 text-warning"></i> Add Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category Name *</label>
                        <input type="text" name="name" id="cat_name" class="form-control" required placeholder="e.g. Plumbing, Fasteners">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea name="description" id="cat_desc" class="form-control" rows="3" placeholder="Category brief details..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetCatForm() {
    document.getElementById('cat_id').value = '0';
    document.getElementById('catModalTitle').innerHTML = '<i class="fa-solid fa-folder-plus me-2 text-warning"></i> Add Category';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_desc').value = '';
}

function editCategory(c) {
    document.getElementById('cat_id').value = c.id;
    document.getElementById('catModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i> Edit Category';
    document.getElementById('cat_name').value = c.name;
    document.getElementById('cat_desc').value = c.description || '';
    
    var myModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    myModal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
