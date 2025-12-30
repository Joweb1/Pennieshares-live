<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/kyc_functions.php';

check_auth();

$currentUser = $_SESSION['user'];
$loggedInUserId = $currentUser['id'];

// Check KYC status
$kyc_status = getKycStatus($loggedInUserId);
if (!$kyc_status || $kyc_status['status'] !== 'verified') {
    $_SESSION['show_kyc_popup'] = true;
    header('Location: /wallet');
    exit;
}

$addMoneyStep = 1; // 1: Enter Amount, 2: Confirm PIN

$addMoneyAmountSV = null;
$addMoneyAmountNaira = null;

// Handle form submission and session management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'submit_amount') {
        $amountSV = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

        if ($amountSV === false || $amountSV <= 0) {
            $_SESSION['add_money_message'] = "Please enter a valid amount.";
            $_SESSION['add_money_status'] = 'error';
        } else {
            $_SESSION['add_money_amount_sv'] = $amountSV;
            $_SESSION['add_money_amount_naira'] = $amountSV * 100;
        }
        header("Location: add_money");
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'confirm_add_money') {
        $amountNaira = $_SESSION['add_money_amount_naira'] ?? null;
        $amountSV = $_SESSION['add_money_amount_sv'] ?? null;
        $pin = trim($_POST['transaction_pin'] ?? '');

        $_SESSION['add_money_status'] = 'error'; // Default to error

        if ($amountNaira === null || $amountNaira <= 0 || empty($pin)) {
            $_SESSION['add_money_message'] = "Invalid details. Please try again.";
        } elseif (!verifyTransactionPin($loggedInUserId, $pin)) {
            $_SESSION['add_money_message'] = "Invalid transaction PIN.";
        } else {
            if ($amountNaira > 20000) {
                // Simulate 2 minute delay
                // In a real application, you might want to use async processing or queue.
                // For this example, we directly set the error message.
                // sleep(120); // Do not use sleep() in web requests
                $_SESSION['add_money_message'] = "Transaction limit exceeded. Please contact a licensed broker to fund your wallet.";
            } else {
                // Proceed with Paystack
                $paystack_data = [
                    'key' => $_ENV['PAYSTACK_PUBLIC_KEY'],
                    'email' => $currentUser['email'],
                    'amount' => $amountNaira * 100, // in kobo
                    'currency' => 'NGN',
                    'ref' => 'pennieshares_add_money_' . uniqid(),
                    'callback_url' => BASE_URL . '/add_money_callback.php'
                ];
                
                // Redirect to a page that will handle the paystack iframe
                $_SESSION['paystack_data'] = $paystack_data;
                header("Location: /paystack_handler.php");
                exit();
            }
        }
        header("Location: add_money");
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'go_back_to_step1') {
        unset($_SESSION['add_money_amount_sv'], $_SESSION['add_money_amount_naira']);
        header("Location: add_money");
        exit();
    }
}

// Determine current step based on session data
if (isset($_SESSION['add_money_amount_sv'])) {
    $addMoneyStep = 2;
    $addMoneyAmountSV = $_SESSION['add_money_amount_sv'];
    $addMoneyAmountNaira = $_SESSION['add_money_amount_naira'];
}

// Read flash messages and then unset them
$addMoneyMessage = $_SESSION['add_money_message'] ?? '';
$addMoneyStatus = $_SESSION['add_money_status'] ?? null;
$addedMoneySV = $_SESSION['add_money_amount_sv'] ?? 0; // Use for final display if success

unset($_SESSION['add_money_message'], $_SESSION['add_money_status'], $_SESSION['add_money_amount_sv'], $_SESSION['add_money_amount_naira']);

// Re-fetch current user data to ensure wallet balance is up-to-date
$currentUser = getUserByIdOrName($loggedInUserId);
$_SESSION['user'] = $currentUser;

require_once __DIR__ . '/../assets/template/intro-template.php';
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          primary: "#10B981", // Emerald 500 - green from Recents tab
          "background-light": "#F3F4F6", // Gray 100
          "background-dark": "#111827", // Gray 900
          "surface-light": "#FFFFFF",
          "surface-dark": "#1F2937", // Gray 800
          "text-primary-light": "#1F2937", // Gray 800
          "text-primary-dark": "#F9FAFB", // Gray 50
          "text-secondary-light": "#6B7280", // Gray 500
          "text-secondary-dark": "#9CA3AF", // Gray 400
          "border-light": "#E5E7EB",
          "border-dark": "#374151",
          "white": "#FFFFFF",
        },
        fontFamily: {
          sans: ["Roboto", "sans-serif"],
        },
        borderRadius: {
          DEFAULT: "0.5rem",
        },
        fontSize: {
          'xs': '0.7rem',
          'sm': '0.8rem',
          'base': '0.9rem',
          'lg': '1.2rem',
          'xl': '1.5rem',
          '2xl': '1.5rem',
          '3xl': '1.8rem',
          '4xl': '1.8rem',
        }
      },
    },
  };
</script>
<style>
    .material-icons {
      font-size: inherit;
    }
    body {
      min-height: max(884px, 100dvh);
      display: flex;
      flex-direction: column;
    }
    .main-content {
      flex-grow: 1;
    }
    .fade-in { animation: fadeIn .4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity:1; transform: translateY(0); } }
    .purchase-modal-overlay {
        position: fixed; /* Changed to fixed for full viewport coverage */
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        padding:0 !important;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }
    .purchase-modal-overlay.visible {
        opacity: 1;
        visibility: visible;
    }
    .purchase-modal-content {
        display:flex;
        flex-direction:column;
        border-radius: 24px;
        padding: 2.5rem;
        margin:0 !important;
        width: 100%;
        max-width: 580px;
        height: 100%;
        max-height: 100%;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        overflow-y: auto; /* Allow scrolling for content */
    }
    html[data-theme="light"] .purchase-modal-content {
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(255, 255, 255, 1);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    }
    html[data-theme="dark"] .purchase-modal-content {
         background: rgba(30, 41, 59, 0.6);
         border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .modal-state { display: none; }
    .modal-state.active { display: block; }
    .processing-animation .spinner {
        width: 80px;
        height: 80px;
        border: 6px solid rgba(var(--accent-color-rgb), 0.2);
        border-top-color: var(--accent-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1.5rem;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .modal-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary-light);
        margin-bottom: 0.5rem;
    }
    html[data-theme="dark"] .modal-title {
        color: var(--text-primary-dark);
    }
    .modal-text {
        font-size: 1.3rem;
        color: var(--text-secondary-light);
    }
    html[data-theme="dark"] .modal-text {
        color: var(--text-secondary-dark);
    }
    .success-animation .success-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 1rem;
    }
    .success-animation .checkmark__circle {
        stroke-dasharray: 166;
        stroke-dashoffset: 166;
        stroke-width: 2;
        stroke-miterlimit: 10;
        stroke: #7ac142;
        fill: none;
        animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }
    .success-animation .checkmark {
        stroke-width: 2;
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        stroke: #7ac142;
        animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }
    @keyframes stroke {
        100% { stroke-dashoffset: 0; }
    }
    .modal-info {
        background-color: #f0f2f5; /* Adjusted for consistency */
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1.5rem;
    }
    html[data-theme="dark"] .modal-info {
        background-color: #1f2937; /* Adjusted for consistency */
    }
    .info-item {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        padding: 0.5rem 0;
    }
    .info-item .label { color: #6B7280; } /* Adjusted for consistency */
    html[data-theme="dark"] .info-item .label { color: #9CA3AF; } /* Adjusted for consistency */
    .info-item .value { font-weight: 600; }
    html[data-theme="dark"] .info-item .value { color: #F9FAFB; }
    .close-modal-btn {
        margin-top: 1.5rem;
        width: 100%;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 48px;
        border-radius: 0.75rem;
        font-size: 1.2rem;
        font-weight: 700;
        padding: 0 1.5rem;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: background-color 0.2s, opacity 0.2s;
    }
    .btn-primary {
        background-color: #10B981; /* Primary color from Tailwind config */
        color: white;
    }
    .btn-primary:hover { background-color: #0e9f71; } /* Darker shade of primary */
    .btn-full-width { width: 100%; }
    .error-animation .error-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 1rem;
    }
    .error-animation .x-mark__circle {
        stroke-dasharray: 166;
        stroke-dashoffset: 166;
        stroke-width: 2;
        stroke-miterlimit: 10;
        stroke: #ef4444;
        fill: none;
        animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }
    .error-animation .x-mark {
        stroke-width: 2;
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        stroke: #ef4444;
        animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }

    .loading-spinner {
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top: 4px solid #fff;
        width: 24px;
        height: 24px;
        animation: spin 1s linear infinite;
    }
</style>

<div class="container mx-auto p-2 max-w-md min-h-screen flex flex-col">
    <?php if ($addMoneyStep === 1): ?>
        <header class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
            <a href="/wallet" class="material-icons text-gray-800 dark:text-gray-200">arrow_back_ios_new</a>
            <h1 class="font-bold text-lg text-primary">Add Money</h1>
            <div class="w-6"></div> <!-- Placeholder for alignment -->
        </header>

        <main class="flex-grow flex flex-col justify-center p-4">
            <?php if (!empty($addMoneyMessage) && $addMoneyStatus === 'error'): ?>
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative mb-4 text-center mx-auto" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($addMoneyMessage); ?></span>
                </div>
            <?php endif; ?>
            <div class="bg-surface-light dark:bg-surface-dark rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6 text-text-primary-light dark:text-text-primary-dark">Enter Amount to Deposit</h2>
                <form method="POST" action="add_money">
                    <input type="hidden" name="action" value="submit_amount">
                    <div class="mb-4">
                        <label for="amount" class="block text-sm font-medium text-text-secondary-light dark:text-text-secondary-dark mb-2">Amount (SV)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-lg font-bold text-text-primary-light dark:text-text-primary-dark">SV</span>
                            <input type="number" name="amount" id="amount" step="0.01" class="pl-12 pr-4 py-3 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-lg text-text-primary-light dark:text-text-primary-dark" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="mb-6 text-center">
                        <p class="text-base text-text-secondary-light dark:text-text-secondary-dark">Equivalent in Naira:</p>
                        <p class="text-2xl font-bold text-primary" id="naira_amount">₦0.00</p>
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 px-4 rounded-lg transition-colors">Next</button>
                </form>
            </div>
        </main>
    <?php elseif ($addMoneyStep === 2): ?>
        <div id="pinModal" class="h-screen flex-col purchase-modal-overlay visible">
            <div id="pinStep" class="flex flex-col h-full purchase-modal-content">
                <div class="flex justify-between items-center mb-4">
                    <form method="POST" action="add_money" class="block">
                        <input type="hidden" name="action" value="go_back_to_step1">
                        <button type="submit" class="text-text-primary-light dark:text-text-primary-dark">
                            <span class="material-icons">arrow_back_ios_new</span>
                        </button>
                    </form>
                    <h2 class="modal-title">Confirm Deposit</h2>
                    <div class="w-6"></div>
                </div>
                <div class="flex-grow flex flex-col justify-center items-center">
                    <div class="text-center">
                        <p class="modal-text">Enter PIN to confirm deposit of</p>
                        <p class="modal-text mb-2"><strong id="confirmAmountPin">SV <?php echo number_format($addMoneyAmountSV, 2); ?> (₦<?php echo number_format($addMoneyAmountNaira, 2); ?>)</strong></p>
                        <div class="my-4 flex justify-center">
                            <div id="pinDisplayContainer" class="flex space-x-2">
                                <input type="password" id="pinInput1" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark focus:outline-none focus:ring-2 focus:ring-primary" readonly>
                                <input type="password" id="pinInput2" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark focus:outline-none focus:ring-2 focus:ring-primary" readonly>
                                <input type="password" id="pinInput3" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark focus:outline-none focus:ring-2 focus:ring-primary" readonly>
                                <input type="password" id="pinInput4" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark focus:outline-none focus:ring-2 focus:ring-primary" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pb-12">
                    <form id="addMoneyForm" method="post">
                        <input type="hidden" name="action" value="confirm_add_money">
                        <input type="hidden" name="transaction_pin" id="transaction_pin_hidden">
                        <button type="submit" id="confirmAddMoneyBtn" class="w-full bg-primary hover:bg-primary/90 text-white py-4 rounded-full text-xl font-medium mb-6 flex items-center justify-center" disabled>
                            <span class="button-text">Confirm</span>
                            <span class="loading-spinner" style="display: none;"></span>
                        </button>
                    </form>
                    <div id="pinNumpad" class="grid grid-cols-3 gap-y-4 text-center text-3xl font-light">
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">1</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">2</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">3</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">4</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">5</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">6</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">7</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">8</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">9</button>
                        <button type="button" id="togglePinVisibility" class="p-2 rounded-full text-text-secondary-light dark:text-text-secondary-dark hover:bg-background-light dark:hover:bg-background-dark transition-colors focus:outline-none">
                            <span class="material-icons" id="pinVisibilityIcon">visibility_off</span>
                        </button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">0</button>
                        <button type="button" id="pinBackspaceBtn" class="text-red-500 p-4 rounded-lg hover:bg-background-light dark:hover:bg-background-dark transition-colors">
                            <span class="material-icons">backspace</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Status Modal -->
<div class="purchase-modal-overlay" id="statusModal">
    <div class="purchase-modal-content">
        <div class="modal-state" id="processingState">
            <div class="processing-animation">
                <div class="loading-spinner"></div>
            </div>
            <h3 class="modal-title">Processing Deposit</h3>
            <p class="modal-text">Please wait while we securely process your transaction.</p>
        </div>
        <div class="modal-state" id="successState">
            <div class="success-animation">
                <svg class="success-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            <h3 class="modal-title">Deposit Successful!</h3>
            <p class="modal-text">Your wallet has been credited.</p>
            <div class="modal-info" id="modal-info">
                <div class="info-item">
                    <span class="label">Amount Deposited:</span>
                    <span class="value" id="successAmount"></span>
                </div>
            </div>
            <button class="btn btn-primary btn-full-width close-modal-btn">Done</button>
        </div>
        <div class="modal-state" id="errorState">
            <div class="error-animation">
                <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="x-mark__circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="x-mark" fill="none" d="M16 16 36 36 M36 16 16 36"/>
                </svg>
            </div>
            <h3 class="modal-title">Deposit Failed</h3>
            <p class="modal-text" id="errorMessage"></p>
            <button class="btn btn-primary btn-full-width close-modal-btn">Try Again</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Theme toggle initialization (if needed, otherwise rely on intro-template)
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyTheme(localStorage.getItem('theme') || 'light');

        // Step 1: Amount Input
        const amountInput = document.getElementById('amount');
        const nairaAmountSpan = document.getElementById('naira_amount');
        if(amountInput) {
            amountInput.addEventListener('input', () => {
                const svAmount = parseFloat(amountInput.value) || 0;
                const nairaAmount = svAmount * 100;
                nairaAmountSpan.textContent = `₦${nairaAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            });
             // Trigger input event on load to set initial Naira value if amount is pre-filled
             amountInput.dispatchEvent(new Event('input'));
        }

        const pinInputs = [
            document.getElementById('pinInput1'),
            document.getElementById('pinInput2'),
            document.getElementById('pinInput3'),
            document.getElementById('pinInput4')
        ];
        const pinNumpad = document.getElementById('pinNumpad');
        const pinBackspaceBtn = document.getElementById('pinBackspaceBtn');
        const confirmAddMoneyBtn = document.getElementById('confirmAddMoneyBtn');
        const transactionPinHidden = document.getElementById('transaction_pin_hidden');
        const togglePinVisibility = document.getElementById('togglePinVisibility');

        if(pinNumpad) {
            pinNumpad.addEventListener('click', (e) => {
                const target = e.target.closest('button');
                if (!target) return;

                if (target.id === 'pinBackspaceBtn') {
                    let currentPin = transactionPinHidden.value;
                    if (currentPin.length > 0) {
                        pinInputs[currentPin.length - 1].value = '';
                        transactionPinHidden.value = currentPin.slice(0, -1);
                        if (currentPin.length > 1) {
                            pinInputs[currentPin.length - 2].focus();
                        } else {
                            pinInputs[0].focus();
                        }
                    }
                    confirmAddMoneyBtn.disabled = true;
                } else if (target.id === 'togglePinVisibility') {
                    const isHidden = pinInputs[0].type === 'password';
                    pinInputs.forEach(input => input.type = isHidden ? 'text' : 'password');
                    target.querySelector('.material-icons').textContent = isHidden ? 'visibility' : 'visibility_off';
                } else {
                    const num = target.textContent.trim();
                    let currentPin = transactionPinHidden.value;
                    if (currentPin.length < 4) {
                        pinInputs[currentPin.length].value = num;
                        transactionPinHidden.value += num;
                        if (currentPin.length < 3) {
                            pinInputs[currentPin.length + 1].focus();
                        } else {
                            pinInputs[currentPin.length].blur();
                        }
                    }
                    if (transactionPinHidden.value.length === 4) {
                        confirmAddMoneyBtn.disabled = false;
                    }
                }
            });
        }
        
        const addMoneyForm = document.getElementById('addMoneyForm');
        if (addMoneyForm) {
            addMoneyForm.addEventListener('submit', function() {
                if (transactionPinHidden.value.length === 4) {
                    confirmAddMoneyBtn.disabled = true;
                    confirmAddMoneyBtn.querySelector('.button-text').style.display = 'none';
                    confirmAddMoneyBtn.querySelector('.loading-spinner').style.display = 'block';
                }
            });
        }

        // Status Modal Handling
        const statusModal = document.getElementById('statusModal');
        const processingState = document.getElementById('processingState');
        const successState = document.getElementById('successState');
        const errorState = document.getElementById('errorState');
        const successSound = new Audio(`${BASE_URL}/assets/sound/new-notification-07-210334.mp3`);
        const errorCallSound = new Audio(`${BASE_URL}/assets/sound/error-call.mp3`);
        successSound.preload = 'auto';

        const addMoneyStatus = <?php echo json_encode($addMoneyStatus); ?>;
        const addMoneyMessage = <?php echo json_encode($addMoneyMessage); ?>;
        const addedMoneySV = <?php echo json_encode($addedMoneySV); ?>; // From PHP, for success message

        if (addMoneyStatus) {
            statusModal.classList.add('visible');
            processingState.classList.add('active');

            // Simulate server processing time, then show actual status
            setTimeout(() => {
                processingState.classList.remove('active');

                if (addMoneyStatus === 'success') {
                    document.getElementById('successAmount').textContent = `SV ${parseFloat(addedMoneySV).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    successState.classList.add('active');
                    if (window.navigator && window.navigator.vibrate) {
                        navigator.vibrate(200);
                    }
                    successSound.play().catch(e => console.error("Sound play failed:", e));

                } else if (addMoneyStatus === 'error') {
                    document.getElementById('errorMessage').textContent = addMoneyMessage || 'An unknown error occurred during deposit.';
                    errorState.classList.add('active');
                    if (window.navigator && window.navigator.vibrate) {
                        navigator.vibrate([100, 50, 100, 50, 100]);
                    }
                    errorCallSound.play().catch(e => console.error("Sound play failed:", e));
                }
            }, 1500); // 1.5 seconds delay for processing feedback
        }

        document.querySelectorAll('.close-modal-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                statusModal.classList.remove('visible');
                // Reset states
                successState.classList.remove('active');
                errorState.classList.remove('active');
                processingState.classList.remove('active');
                // Optionally, redirect to reset the form or go back to step 1
                // window.location.href = 'add_money'; // Reload page to clear session messages
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../assets/template/end-template.php'; ?>