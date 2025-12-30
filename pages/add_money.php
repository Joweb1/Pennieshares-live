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

$addMoneyMessage = $_SESSION['add_money_message'] ?? '';
$addMoneyStatus = $_SESSION['add_money_status'] ?? null;

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
        $pin = trim($_POST['transaction_pin'] ?? '');

        if ($amountNaira === null || $amountNaira <= 0 || empty($pin)) {
            $_SESSION['add_money_message'] = "Invalid details. Please try again.";
            $_SESSION['add_money_status'] = 'error';
        } elseif (!verifyTransactionPin($loggedInUserId, $pin)) {
            $_SESSION['add_money_message'] = "Invalid transaction PIN.";
            $_SESSION['add_money_status'] = 'error';
        } else {
            if ($amountNaira > 20000) {
                // Simulate 2 minute delay
                sleep(120);
                $_SESSION['add_money_message'] = "Transaction limit exceeded. Please contact a licensed broker to fund your wallet.";
                $_SESSION['add_money_status'] = 'error';
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

if (isset($_SESSION['add_money_amount_sv'])) {
    $addMoneyStep = 2;
    $addMoneyAmountSV = $_SESSION['add_money_amount_sv'];
    $addMoneyAmountNaira = $_SESSION['add_money_amount_naira'];
}

$currentUser = getUserByIdOrName($loggedInUserId);
$_SESSION['user'] = $currentUser;

require_once __DIR__ . '/../assets/template/intro-template.php';
?>
<div class="container mx-auto p-4 max-w-md">
    <?php if ($addMoneyStep === 1): ?>
        <h1 class="text-xl font-bold text-center mb-4">Add Money</h1>
        <?php if (!empty($addMoneyMessage) && $addMoneyStatus === 'error'): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($addMoneyMessage); ?></span>
            </div>
        <?php endif; ?>
        <form method="POST" action="add_money">
            <input type="hidden" name="action" value="submit_amount">
            <div class="mb-4">
                <label for="amount" class="block text-sm font-medium text-gray-700">Amount (SV)</label>
                <input type="number" name="amount" id="amount" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-500">Equivalent in Naira: <span id="naira_amount" class="font-bold">0.00</span></p>
            </div>
            <button type="submit" class="w-full bg-primary text-white font-bold py-2 px-4 rounded">Next</button>
        </form>
    <?php elseif ($addMoneyStep === 2): ?>
        <div id="pinModal" class="bg-background-light dark:bg-surface-dark text-gray-900 dark:text-white h-screen flex-col z-50 purchase-modal-overlay visible">
            <div id="pinStep" class="flex flex-col h-full purchase-modal-content">
                <div class="flex justify-between items-center mb-4">
                    <form method="POST" action="add_money">
                        <input type="hidden" name="action" value="go_back_to_step1">
                        <button type="submit" class="text-text-primary-light dark:text-text-primary-dark">
                            <span class="material-icons">arrow_back</span>
                        </button>
                    </form>
                    <h2 class="modal-title">Confirm Deposit</h2>
                    <div></div>
                </div>
                <div class="flex-grow flex flex-col justify-center items-center">
                    <div class="text-center">
                        <p class="modal-text">Enter PIN to confirm deposit of</p>
                        <p class="modal-text mb-2"><strong id="confirmAmountPin">SV <?php echo number_format($addMoneyAmountSV, 2); ?> (₦<?php echo number_format($addMoneyAmountNaira, 2); ?>)</strong></p>
                        <div class="my-4 flex justify-center">
                            <div id="pinDisplayContainer" class="flex space-x-2">
                                <input type="password" id="pinInput1" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark" readonly>
                                <input type="password" id="pinInput2" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark" readonly>
                                <input type="password" id="pinInput3" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark" readonly>
                                <input type="password" id="pinInput4" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold bg-surface-light dark:bg-surface-dark rounded-lg border border-border-light dark:border-border-dark" readonly>
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
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">1</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">2</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">3</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">4</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">5</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">6</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">7</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">8</button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">9</button>
                        <button type="button" id="togglePinVisibility" class="p-2 rounded-full text-gray-500 dark:text-gray-400"><span class="material-icons" id="pinVisibilityIcon">visibility_off</span></button>
                        <button type="button" class="text-text-primary-light dark:text-text-primary-dark">0</button>
                        <button type="button" id="pinBackspaceBtn" class="text-red-500"><span class="material-icons">backspace</span></button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const amountInput = document.getElementById('amount');
        const nairaAmountSpan = document.getElementById('naira_amount');
        if(amountInput) {
            amountInput.addEventListener('input', () => {
                const svAmount = parseFloat(amountInput.value) || 0;
                const nairaAmount = svAmount * 100;
                nairaAmountSpan.textContent = nairaAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        }

        const pinInputs = [document.getElementById('pinInput1'), document.getElementById('pinInput2'), document.getElementById('pinInput3'), document.getElementById('pinInput4')];
        const pinNumpad = document.getElementById('pinNumpad');
        const pinBackspaceBtn = document.getElementById('pinBackspaceBtn');
        const confirmAddMoneyBtn = document.getElementById('confirmAddMoneyBtn');
        const transactionPinHidden = document.getElementById('transaction_pin_hidden');
        const togglePinVisibility = document.getElementById('togglePinVisibility');

        if(pinNumpad) {
            pinNumpad.addEventListener('click', (e) => {
                if (e.target.tagName === 'BUTTON' && e.target.id !== 'pinBackspaceBtn' && e.target.id !== 'togglePinVisibility') {
                    const num = e.target.textContent;
                    let currentPin = transactionPinHidden.value;
                    if (currentPin.length < 4) {
                        pinInputs[currentPin.length].value = num;
                        transactionPinHidden.value += num;
                    }
                    if (transactionPinHidden.value.length === 4) {
                        confirmAddMoneyBtn.disabled = false;
                    }
                }
            });
        }

        if(pinBackspaceBtn) {
            pinBackspaceBtn.addEventListener('click', () => {
                let currentPin = transactionPinHidden.value;
                if (currentPin.length > 0) {
                    pinInputs[currentPin.length - 1].value = '';
                    transactionPinHidden.value = currentPin.slice(0, -1);
                }
                confirmAddMoneyBtn.disabled = true;
            });
        }
        
        if (togglePinVisibility) {
            togglePinVisibility.addEventListener('click', (e) => {
                const isHidden = pinInputs[0].type === 'password';
                pinInputs.forEach(input => input.type = isHidden ? 'text' : 'password');
                e.currentTarget.querySelector('.material-icons').textContent = isHidden ? 'visibility' : 'visibility_off';
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
    });
</script>

<?php require_once __DIR__ . '/../assets/template/end-template.php'; ?>
