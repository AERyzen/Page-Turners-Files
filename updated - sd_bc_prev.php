<?php

    session_start();
    include('../db_config.php');

    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    if (!isset($_SESSION['admin_id'])) {
        header("Location: ../index.php?logout=true");
        exit();
    }

    $admin_id = $_SESSION['admin_id'];
    $sql = "SELECT username FROM admin_tbl WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        session_destroy();
        header("Location: ../index.php?error=unauthorized");
        exit();
    }

    $admin_username = $user['username'];
    $stmt->close();

    // Add Book
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
        // Check if ISBN already exists
        $isbn_check = $_POST['isbn'];
        $check_stmt = $conn->prepare("SELECT 1 FROM bookshelf_tbl WHERE ISBN = ?");
        $check_stmt->bind_param("s", $isbn_check);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // ISBN already exists - show alert and redirect
            echo "<script>
                alert('Failed to add book. ISBN is already registered.');
                window.location.href = 'admin_mbc.php';
            </script>";
            $check_stmt->close();
            exit();
        }

        $check_stmt->close();

        // Proceed with adding the book
        $stmt = $conn->prepare("INSERT INTO bookshelf_tbl (Book, Title, ISBN, Author, Edition, Publisher, Genre, `Desc`, Date_Published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $_POST['book'], $_POST['title'], $_POST['isbn'], $_POST['author'], $_POST['edition'], $_POST['publisher'], $_POST['genre'], $_POST['desc'], $_POST['date_published']);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_mbc.php");
        exit();
    }

    // Update Book
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
        $stmt = $conn->prepare("UPDATE bookshelf_tbl SET Book=?, Title=?, ISBN=?, Author=?, Edition=?, Publisher=?, Genre=?, `Desc`=?, Date_Published=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $_POST['book'], $_POST['title'], $_POST['isbn'], $_POST['author'], $_POST['edition'], $_POST['publisher'], $_POST['genre'], $_POST['desc'], $_POST['date_published'], $_POST['id']);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_mbc.php");
        exit();
    }

    // Delete Book with Confirmation
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        $book_id_to_delete = $_GET['id'];
        echo "<script>
            if (confirm('Are you sure you want to delete this book?')) {
                window.location.href = 'admin_mbc.php?action=confirmed_delete&id=" . $book_id_to_delete . "';
            } else {
                window.location.href = 'admin_mbc.php';
            }
        </script>";
        exit();
    }

    // Handle Confirmed Deletion
    if (isset($_GET['action']) && $_GET['action'] === 'confirmed_delete' && isset($_GET['id'])) {
        $stmt = $conn->prepare("DELETE FROM bookshelf_tbl WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_mbc.php");
        exit();
    }

    $books_result = $conn->query("SELECT * FROM bookshelf_tbl ORDER BY Date_Published DESC");
    $total_books = $books_result->num_rows;

?>

<!DOCTYPE html>

<html lang="en">

    <head>

        <title>Admin Panel | Manage Book Catalog</title>

        <!-- meta tags -->
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Favicon -->
        <link rel="icon" href="../web resources/images/logo.png" type="image/png">

        <!-- CSS/stylesheet -->
        <link href="../web resources/main.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Allura&family=Playfair+Display&display=swap" rel="stylesheet">

        <style>
            .body {
            padding: 70px;
            }

            .navbar {
            position: fixed; /* Ensure it stays fixed at the top */
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000; /* Give the navbar a high z-index to be on top */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            background-color: #766b5d;
            }

            .navbar-brand{
            font-family:"Playfair Display";
            font-size: 30px;
            text-rendering: optimizeLegibility;
            croll-behavior: smooth;
            font-weight: bold;
            color: #d4c7af;
            }


            .sidebar {
            position: fixed;
            top: 50px; /* Adjust top to be below the navbar */
            left: 0;
            width: 250px;
            height: 100%;
            background-color: #f8f9fa;
            padding-top: 20px;
            border-right: 1px solid #ddd;
            transform: translateX(0);
            transition: transform 0.3s ease;
            font-family:'Playfair Display';
            }

            .sidebar a {
            display: block;
            padding: 10px 20px;
            text-decoration: none;
            color: #333;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #fff;
            }

            .sidebar a:hover {
            background-color: #d4c7af;
            color: white;
            }


            .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            background-color:rgb(255, 255, 255);
            font-family:'Playfair Display'; 
            color: #766b5d;
            }

             /* Card Deck Layout */
            .card-deck {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 0; /* Remove margin */
            font-family: 'Playfair Display';
            font-color: #766b5d;
            }

            /* Individual Card */
            .card {
            width: 18rem;
            display: flex;
            flex-direction: column;
            height: 530px;
            margin-bottom: 1rem;
            background-color: #d4c7af;
            font-color: #766b5d;
            }

            /* Hover effect for book cards (lift and shadow) */
            .card:hover { 
            transform: translateY(-3px), translateX(-0px); /* Lift the card */
            box-shadow: 23px 26px 11px -5px rgba(0, 0, 0, 0.75); /* Custom shadow */
            -webkit-box-shadow: 23px 26px 11px -5px rgba(0, 0, 0, 0.75);
            -moz-box-shadow: 23px 26px 11px -5px rgba(0, 0, 0, 0.75);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            }

            .card img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            }

            .card-body p {
            margin-bottom: 0.3rem;7af
            }

            .card-footer {
            text-align: center;
            }

            .admin_mbc-card{
                background-color: #d4c7af;

            }

             /* Mobile View */
            @media (max-width: 768px) {
            .sidebar {
                display: none; /* Hide the sidebar completely on mobile */
                transform: translateX(-250px); /* Keep for potential future transitions */
            }

            .content {
                margin-left: 0; /* Content takes full width on mobile */
            }

            .dropdown-menu {
                position: fixed; /* Fixed to the viewport */
                top: 56px; /* Set top to the height of the navbar */
                left: 0;
                background-color: #f8f9fa;
                border: 1px solid #ddd;
                width: 100%;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                display: none; /* Initially hidden */
                overflow: hidden; /* For slide down animation */
                max-height: 0;
                transition: max-height 0.3s ease-in-out;
                z-index: 999; /* Below the navbar */ 
            }

            .dropdown-menu.show {
                display: block;
                max-height: 500px; /* Adjust as needed */
            }

            .dropdown-item {
                display: block;
                padding: 10px 20px;
                text-decoration: none;
                color: #333;
            }

            .dropdown-item:hover {
                background-color: #007bff;
                color: white;
            }

            /* Card Styling for Mobile */
            .card-deck {
                flex-direction: column; /* Stack cards vertically on mobile */
                gap: 1rem; /* Add some space between stacked cards */
            }

            .card {
                width: 100%; /* Make cards take full width on mobile */
                height: auto; /* Allow height to adjust based on content */
                margin-bottom: 1rem; /* Maintain spacing between cards */
            }

            .card img {
                height: auto; /* Adjust image height if needed, or keep auto */
                max-height: 250px; /* Optional: Set a maximum image height */
                object-fit: contain; /* Ensure the image fits within the bounds */
            }

            /* Disable hover effect on mobile */
            .card:hover {
                transform: none;
                box-shadow: none;
                transition: none;
            }

            .card-footer .btn {
                font-size: 1rem; /* Adjust button font size for mobile */
                padding: 8px 16px; /* Adjust button padding for better touch targets */
            }
            }

        /* Hide hamburger button and dropdown on larger screens */
        @media (min-width: 769px) {
            .navbar .hamburger-btn {
                display: none;
            }
            .dropdown-menu {
                display: none !important; /* Ensure dropdown is hidden on desktop */
            }
            }

        /* Loading screen styles */
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            background-size: 50px 50px;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            }

            /* Search Highlight */
            mark {
            background-color: #ffff66; /* brighter yellow */
            color: black; /* text color inside highlight */
            padding: 0 2px;
            border-radius: 2px;
            }
        </style>
    </head>

    <body>

        <!-- Load Screen -->
        <div id="loading">
            <img src="../web resources/others/Loading_icon.gif">
        </div>

        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light fixed-top">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <img src="../web resources/images/page-logo.png" alt="Logo" class="navbar-logo" style="height: 40px; margin-right: 10px;">
                    <span class="navbar-brand mb-0 h1">Page Turners</span>
                </div>
                <div class="d-flex align-items-center">
                    <a href="../index.php" class="btn btn-primary bg-light text-dark me-2">Logout</a>
                    <button class="navbar-toggler hamburger-btn me-2" type="button" aria-expanded="false" aria-label="Toggle navigation" onclick="toggleDropdown()">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>
            </div>
            <div class="dropdown-menu" id="mobileDropdown">
                <p></p>
                <a href="admin_panel.php" class="dropdown-item">Admin Panel</a>
                <a href="admin_mue.php" class="dropdown-item">Manage User Entries</a>
                <p class="active-item ms-3"><b>Manage Book Catalog</b></p>
                <a href="admin_rr.php" class="dropdown-item">Reservation Requests</a>
                <a href="admin_cpu.php" class="dropdown-item">Confirm Pick-Ups</a>
            </div>
        </nav>

        <!-- Sidebar -->
        <div class="sidebar">
            <a href="admin_panel.php" class="dropdown-item">Admin Panel</a>
            <a href="admin_mue.php" class="dropdown-item">Manage User Entries</a>
            <p class="ms-3 mt-3 mb-3"><b>Manage Book Catalog</b></p>
            <a href="admin_rr.php" class="dropdown-item">Reservation Requests</a>
            <a href="admin_cpu.php" class="dropdown-item">Confirm Pick-Ups</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h1 style="font-family: Allura;"><b>Book Catalog</b></h1>
            <p>Add, update, or delete books and manage your book catalog effectively.</p>
            
            <div class="mb-3 mt-3 w-50">
                <input type="text" class="form-control w-50" id="searchInput_admin_mbc" placeholder="Search by title, author, ISBN, etc.">
            </div>

            <!-- Shows X of X items -->
            <p id="itemCount_admin_mbc" class="text-start text-muted">Showing <span id="visibleCount_admin_mbc"><?php echo $total_books; ?></span> of <span id="totalCount_admin_mbc"><?php echo $total_books; ?></span> items</p>
            
            <p id="noResults_admin_mbc" class="text-center text-muted" style="display: none;">No matching books found.</p>

            <div class="admin_mbc-card-deck mt-4" id="bookDeck_admin_mbc">
                <!-- Add Card (not counted) -->
                <div class="admin_mbc-card admin_mbc-add-card" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <h1 class="text-center text-secondary"><b>+</b></h1>
                </div>

                <?php

                    if ($total_books > 0) {
                        while ($row = $books_result->fetch_assoc()) {
                            echo "<div class='admin_mbc-card'>
                                    <img src='{$row['Book']}' alt='Book Image'>
                                    <div class='admin_mbc-card-body my-3'>
                                        <p><strong>Title:</strong> {$row['Title']}</p>
                                        <p><strong>ISBN:</strong> {$row['ISBN']}</p>
                                        <p><strong>Author:</strong> {$row['Author']}</p>
                                        <p><strong>Edition:</strong> {$row['Edition']}</p>
                                        <p><strong>Publisher:</strong> {$row['Publisher']}</p>
                                        <p><strong>Genre:</strong> {$row['Genre']}</p>
                                        <p><strong>Description:</strong> {$row['Desc']}</p>
                                    </div>
                                    <div class='admin_mbc-card-footer'>
                                        <button class='btn btn-success btn-sm w-100 mb-1'
                                                data-bs-toggle='modal'
                                                data-bs-target='#updateBookModal{$row['id']}'>
                                            Update
                                        </button>
                                        <a href='admin_mbc.php?action=delete&id=" . $row['id'] . "' class='btn btn-danger btn-sm w-100'>Delete</a>
                                    </div>
                                </div>
                    
                                <div class='modal fade' id='updateBookModal{$row['id']}' tabindex='-1' aria-labelledby='updateBookModalLabel{$row['id']}' aria-hidden='true'>
                                    <div class='modal-dialog'>
                                        <div class='modal-content'>
                                            <form method='POST' action='admin_mbc.php'>
                                                <input type='hidden' name='action' value='update'>
                                                <input type='hidden' name='id' value='{$row['id']}'>
                                                <div class='modal-header'>
                                                    <h5 class='modal-title' id='updateBookModalLabel{$row['id']}'>Update Book</h5>
                                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                </div>
                                                <div class='modal-body'>
                                                    <div class='mb-3'>
                                                        <label for='update_book_{$row['id']}' class='form-label'>Book Image URL</label>
                                                        <input type='text' class='form-control' id='update_book_{$row['id']}' name='book' value='" . htmlspecialchars($row['Book']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_title_{$row['id']}' class='form-label'>Title</label>
                                                        <input type='text' class='form-control' id='update_title_{$row['id']}' name='title' value='" . htmlspecialchars($row['Title']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_isbn_{$row['id']}' class='form-label'>ISBN</label>
                                                        <input type='text' class='form-control' id='update_isbn_{$row['id']}' name='isbn' value='" . htmlspecialchars($row['ISBN']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_author_{$row['id']}' class='form-label'>Author</label>
                                                        <input type='text' class='form-control' id='update_author_{$row['id']}' name='author' value='" . htmlspecialchars($row['Author']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_edition_{$row['id']}' class='form-label'>Edition</label>
                                                        <input type='text' class='form-control' id='update_edition_{$row['id']}' name='edition' value='" . htmlspecialchars($row['Edition']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_publisher_{$row['id']}' class='form-label'>Publisher</label>
                                                        <input type='text' class='form-control' id='update_publisher_{$row['id']}' name='publisher' value='" . htmlspecialchars($row['Publisher']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_genre_{$row['id']}' class='form-label'>Genre</label>
                                                        <input type='text' class='form-control' id='update_genre_{$row['id']}' name='genre' value='" . htmlspecialchars($row['Genre']) . "' required>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_desc_{$row['id']}' class='form-label'>Description</label>
                                                        <textarea class='form-control' id='update_desc_{$row['id']}' name='desc' rows='5' required>" . htmlspecialchars($row['Desc']) . "</textarea>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label for='update_date_published_{$row['id']}' class='form-label'>Date Published</label>
                                                        <input type='date' class='form-control' id='update_date_published_{$row['id']}' name='date_published' value='" . htmlspecialchars($row['Date_Published']) . "' required>
                                                    </div>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='submit' class='btn btn-primary'>Update Book</button>
                                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                        }
                    } else {
                        echo "<p class='text-center'>No books available.</p>";
                    }

                ?>
            </div>
        </div>

        <!-- Error message if ISBN already exists -->
        <?php if (isset($isbn_error)): ?>
            <div class="error-message" style="color: red; font-weight: bold;">
                <?php echo $isbn_error; ?>
            </div>
        <?php endif; ?>

        <!-- Modal for Adding a Book -->
        <div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addBookModalLabel">Add New Book</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Add Book Form -->
                        <form id="addBookForm" method="POST" action="admin_mbc.php">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label for="book" class="form-label">Book Image URL</label>
                                <input type="text" class="form-control" id="book" name="book" required>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="isbn" name="isbn" required>
                            </div>
                            <div class="mb-3">
                                <label for="author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="author" name="author" required>
                            </div>
                            <div class="mb-3">
                                <label for="edition" class="form-label">Edition</label>
                                <input type="text" class="form-control" id="edition" name="edition" required>
                            </div>
                            <div class="mb-3">
                                <label for="publisher" class="form-label">Publisher</label>
                                <input type="text" class="form-control" id="publisher" name="publisher" required>
                            </div>
                            <div class="mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <input type="text" class="form-control" id="genre" name="genre" required>
                            </div>
                            <div class="mb-3">
                                <label for="desc" class="form-label">Description</label>
                                <textarea class="form-control" id="desc" name="desc" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="date_published" class="form-label">Date Published</label>
                                <input type="date" class="form-control" id="date_published" name="date_published" required>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Add Book</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Updating a Book -->
        <div class="modal fade" id="updateBookModal" tabindex="-1" aria-labelledby="updateBookModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="updateBookForm" method="POST" action="admin_mbc.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" id="update_id" name="id">
                        <div class="modal-header">
                            <h5 class="modal-title" id="updateBookModalLabel">Update Book</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="update_book" class="form-label">Book Image URL</label>
                                <input type="text" class="form-control" id="update_book" name="book" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="update_title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="update_isbn" name="isbn" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_author" class="form-label">Author</label>
                                <input type="text" class="form-control" id="update_author" name="author" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_edition" class="form-label">Edition</label>
                                <input type="text" class="form-control" id="update_edition" name="edition" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_publisher" class="form-label">Publisher</label>
                                <input type="text" class="form-control" id="update_publisher" name="publisher" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_genre" class="form-label">Genre</label>
                                <input type="text" class="form-control" id="update_genre" name="genre" required>
                            </div>
                            <div class="mb-3">
                                <label for="update_desc" class="form-label">Description</label>
                                <textarea class="form-control" id="update_desc" name="desc" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="update_date_published" class="form-label">Date Published</label>
                                <input type="date" class="form-control" id="update_date_published" name="date_published" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Update Book</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- JavaScript -->
        <script src="../web resources/main.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

        <!-- Exclusive admin_mbc script -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('searchInput_admin_mbc');
                const allCards = document.querySelectorAll('.admin_mbc-card');
                const dataCards = document.querySelectorAll('.admin_mbc-card:not(.admin_mbc-add-card)');
                const visibleCountElem = document.getElementById('visibleCount_admin_mbc');
                const noResults = document.getElementById('noResults_admin_mbc');

                // Store original text of each <p> in data attribute (only real book cards)
                dataCards.forEach(card => {
                    const pTags = card.querySelectorAll('p');
                    pTags.forEach(p => {
                        p.dataset.originalText = p.textContent;
                    });
                });

                // Highlight matched text
                const highlightText = (text, term) => {
                    if (!term) return text;
                    const regex = new RegExp(`(${term})`, 'gi');
                    return text.replace(regex, '<mark>$1</mark>');
                };

                // Live search + highlight
                searchInput.addEventListener('keyup', function () {
                    const searchValue = this.value.toLowerCase().trim();
                    let visibleCount = 0;

                    dataCards.forEach(card => {
                        const cardText = card.textContent.toLowerCase();

                        if (cardText.includes(searchValue)) {
                            card.style.display = '';
                            visibleCount++;

                            const pTags = card.querySelectorAll('p');
                            pTags.forEach(p => {
                                const original = p.dataset.originalText;
                                p.innerHTML = highlightText(original, searchValue);
                            });

                        } else {
                            card.style.display = 'none';
                        }
                    });

                    visibleCountElem.textContent = visibleCount;
                    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
                });

                // Filter books based on search and update the visible count
                document.getElementById('searchInput_admin_mbc').addEventListener('keyup', function() {
                    var searchInput = this.value.toLowerCase();
                    var books = document.querySelectorAll('.admin_mbc-card');
                    var visibleCount = 0;

                    books.forEach(function(book) {
                        var bookDetails = book.textContent.toLowerCase();
                        if (bookDetails.includes(searchInput)) {
                            book.style.display = '';
                            visibleCount++;
                        } else {
                            book.style.display = 'none';
                        }
                    });

                    // Show results
                    document.getElementById('visibleCount_admin_mbc').textContent = visibleCount;
                    document.getElementById('totalCount_admin_mbc').textContent = books.length;
                    document.getElementById('noResults_admin_mbc').style.display = visibleCount === 0 ? 'block' : 'none';
                });
            });
        </script>

    </body>

</html>
