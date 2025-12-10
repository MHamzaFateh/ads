<?php
require_once 'config.php';
requireLogin();

$db = getDB();

// Handle ad creation and setting default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_ad') {
        try {
            $videoLink = filter_var($_POST['video_link'], FILTER_SANITIZE_URL);
            $gender = $_POST['gender'];
            $ageGroups = isset($_POST['age_groups']) ? implode(',', $_POST['age_groups']) : '';
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            
            // If this is being set as default, first unset any existing default
            if ($isDefault) {
                $db->exec('UPDATE ads SET is_default = 0 WHERE is_default = 1');
            }
            
            $stmt = $db->prepare('INSERT INTO ads (video_link, gender, age_groups, is_default) VALUES (:video_link, :gender, :age_groups, :is_default)');
            $stmt->execute([
                ':video_link' => $videoLink,
                ':gender' => $gender,
                ':age_groups' => $ageGroups,
                ':is_default' => $isDefault
            ]);
            
            header('Location: index.php?success=ad_created');
            exit();
        } catch (PDOException $e) {
            die('Error creating ad: ' . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'set_default' && isset($_POST['ad_id'])) {
        try {
            // Unset any existing default
            $db->exec('UPDATE ads SET is_default = 0 WHERE is_default = 1');
            
            // Set the selected ad as default
            $stmt = $db->prepare('UPDATE ads SET is_default = 1 WHERE id = :id');
            $stmt->execute([':id' => (int)$_POST['ad_id']]);
            
            header('Location: index.php?success=default_updated');
            exit();
        } catch (PDOException $e) {
            die('Error updating default ad: ' . $e->getMessage());
        }
    }
}

// Handle ad deletion
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $db->prepare('DELETE FROM ads WHERE id = :id');
        $stmt->execute([':id' => $id]);
        
        header('Location: index.php?success=ad_deleted');
        exit();
    } catch (PDOException $e) {
        die('Error deleting ad: ' . $e->getMessage());
    }
}

// Fetch all ads
$ads = [];
try {
    $stmt = $db->query('SELECT * FROM ads ORDER BY created_at DESC');
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching ads: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Management - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-indigo-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Ad Management System</h1>
            <div class="flex items-center space-x-4">
                <a href="preview.php" target="_blank" class="px-3 py-1 bg-indigo-700 hover:bg-indigo-800 rounded text-white text-sm font-medium">
                    Preview Ads
                </a>
                <span class="mr-4">Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                <a href="logout.php" class="text-indigo-200 hover:text-white">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php 
                if ($_GET['success'] === 'ad_created') {
                    echo 'Ad created successfully!';
                } elseif ($_GET['success'] === 'ad_deleted') {
                    echo 'Ad deleted successfully!';
                } elseif ($_GET['success'] === 'default_updated') {
                    echo 'Default ad updated successfully!';
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Create Ad Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-xl font-semibold mb-4">Create New Ad</h2>
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="action" value="create_ad">
                
                <div>
                    <label for="video_link" class="block text-sm font-medium text-gray-700">Video Link</label>
                    <input type="url" id="video_link" name="video_link" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Gender</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="male" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ml-2">Male</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="female" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ml-2">Female</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="all" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ml-2">All Genders</span>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Age Groups</label>
                    <div class="grid grid-cols-2 gap-2">
                        <?php 
                        $ageGroups = [
                            'child' => 'Child (0-12)',
                            'teenage' => 'Teenage (13-19)',
                            'young' => 'Young (20-35)',
                            'adult' => 'Adult (36+)'
                        ];
                        
                        foreach ($ageGroups as $value => $label):
                        ?>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="age_groups[]" value="<?php echo $value; ?>" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="ml-2"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="pt-2 space-y-4">
                    <div class="flex items-center">
                        <input id="is_default" name="is_default" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="is_default" class="ml-2 block text-sm text-gray-700">
                            Set as default ad (will be shown when no other ads match the viewer's profile)
                        </label>
                    </div>
                    <div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create Ad
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Ads List -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Existing Ads</h2>
            
            <?php if (empty($ads)): ?>
                <p class="text-gray-500">No ads found. Create your first ad above.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Video Link</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age Groups</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($ads as $ad): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="<?php echo htmlspecialchars($ad['video_link']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 truncate block max-w-xs">
                                            <?php echo htmlspecialchars($ad['video_link']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        switch($ad['gender']) {
                                            case 'male': echo 'Male'; break;
                                            case 'female': echo 'Female'; break;
                                            default: echo 'All Genders';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $ageGroups = explode(',', $ad['age_groups']);
                                        $ageGroupLabels = [];
                                        $ageGroupMap = [
                                            'child' => 'Child (0-12)',
                                            'teenage' => 'Teenage (13-19)',
                                            'young' => 'Young (20-35)',
                                            'adult' => 'Adult (36+)'
                                        ];
                                        
                                        foreach ($ageGroups as $group) {
                                            if (isset($ageGroupMap[$group])) {
                                                $ageGroupLabels[] = $ageGroupMap[$group];
                                            } else {
                                                $ageGroupLabels[] = ucfirst($group); // Fallback for any unexpected values
                                            }
                                        }
                                        
                                        echo !empty($ageGroupLabels) ? implode(', ', $ageGroupLabels) : 'All Ages';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($ad['is_default']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Default Ad
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 space-x-2">
                                        <form method="POST" action="index.php" class="inline">
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900" <?php echo $ad['is_default'] ? 'disabled' : ''; ?>>
                                                Set as Default
                                            </button>
                                        </form>
                                        |
                                        <a href="?delete=<?php echo $ad['id']; ?>" onclick="return confirm('Are you sure you want to delete this ad?')" class="text-red-600 hover:text-red-900">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
