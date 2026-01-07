<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/kyc_functions.php';

check_auth();

if (php_sapi_name() !== 'cli') {
    $currentUser = $_SESSION['user'];
    $loggedInUserId = $currentUser['id'];

    // Check KYC status
    $kyc_status = getKycStatus($loggedInUserId);
    if (!$kyc_status || $kyc_status['status'] !== 'verified') {
        $_SESSION['show_kyc_popup'] = true;
        header('Location: /wallet');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Money</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        :root {
            --primary-text: #1f2937;
            --secondary-text: #6b7280;
            --background: #f9fafb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --primary-accent: #10B981;
            --popup-overlay-bg: rgba(0, 0, 0, 0.5);
            --numpad-hover-bg: #f3f4f6; /* Tailwind gray-100 */
        }
        html[data-theme="dark"] {
            --primary-text: #f9fafb;
            --secondary-text: #9ca3af;
            --background: #111827;
            --surface: #1f2937;
            --border: #374151;
            --popup-overlay-bg: rgba(0, 0, 0, 0.7);
            --numpad-hover-bg: rgb(55 65 81); /* Tailwind gray-700 */
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background);
            color: var(--primary-text);
        }
        .numpad-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
            font-size: 1.75rem;
            font-weight: 300;
            padding: 0 1rem;
        }

        .numpad-button:hover {
            background-color: var(--numpad-hover-bg);
        }

        .numpad-button {
            transition: background-color 0.2s ease;
        }

        .numpad-button:hover {
            background-color: var(--numpad-hover-bg);
        }


        /* Loading Spinner CSS */
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid var(--primary-accent);
            width: 24px;
            height: 24px;
            -webkit-animation: spin 1s linear infinite;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }

        @-webkit-keyframes spin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Popup Message CSS */
        .popup-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--popup-overlay-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }

        .popup-container.show {
            visibility: visible;
            opacity: 1;
        }

        .popup-message {
            background-color: var(--surface);
            color: var(--primary-text);
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 80%;
        }
    </style>
  </head>
  <body>
    <!-- Popup Container -->
    <div id="customPopup" class="popup-container">
        <div class="popup-message">
            <p id="popupMessageText"></p>
            <button id="popupCloseBtn" class="mt-4 px-4 py-2 rounded-md" style="background-color: var(--primary-accent); color: white;">OK</button>
        </div>
    </div>
    <!-- Add Money Modal -->
    <div id="addMoneyModal" style="position: fixed; inset: 0; background-color: var(--background); color: var(--primary-text); display: flex; flex-direction: column; z-index: 50;">
        <!-- Step 1: Amount Entry -->
        <div id="amountStep" style="display: flex; flex-direction: column; height: 100%; padding: 1rem;">
            <header class="flex justify-between items-center mb-4">
                <a href="/wallet" class="material-icons">close</a>
                <h2 class="text-lg font-bold">Add Money</h2>
                <div class="w-6"></div>
            </header>
            <main class="flex-grow flex flex-col justify-center items-center">
                <div class="text-center">
                    <p class="text-lg" style="color: var(--secondary-text);">Amount in SV</p>
                    <div class="flex items-center justify-center my-2">
                        <span id="amountDisplay" class="text-6xl font-bold">0</span>
                    </div>
                    <p id="nairaEquivalent" class="text-sm" style="color: var(--secondary-text);">₦0.00</p>
                </div>
            </main>
            <div class="pb-4">
                <div class="pb-4">
                <button id="goToPinStepBtn" class="w-full py-4 rounded-full text-lg font-medium mb-6" style="background-color: var(--primary-accent); color: white;" disabled>
                    Next
                </button>
                <div class="numpad-grid">
                    <button type="button" class="p-4 rounded-lg numpad-button">1</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">2</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">3</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">4</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">5</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">6</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">7</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">8</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">9</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">.</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">0</button>
                    <button type="button" id="amountBackspaceBtn" class="text-red-500 p-4 rounded-lg numpad-button"><span class="material-icons">backspace</span></button>
                </div>
            </div>
        </div>
        </div>

        <!-- Step 2: PIN Entry -->
        <div id="pinStep" style="display: none; flex-direction: column; height: 100%; padding: 1rem;">
            <header class="flex justify-between items-center mb-4">
                <button id="backToAmountStepBtn" class="material-icons">arrow_back_ios_new</button>
                <h2 class="text-lg font-bold">Confirm Deposit</h2>
                <div class="w-6"></div>
            </header>
            <main class="flex-grow flex flex-col justify-center items-center">
                <div class="text-center">
                    <p class="text-lg" style="color: var(--secondary-text);">You are depositing</p>
                    <p id="confirmAmountText" class="text-xl font-bold mb-2"></p>
                    <p id="confirmNairaEquivalent" class="text-sm" style="color: var(--secondary-text);"></p>
                    <p class="text-lg mt-4" style="color: var(--secondary-text);">Total amount to pay (NGN)</p>
                    <p id="totalPayAmountText" class="text-2xl font-bold mb-2 text-primary-accent"></p>
                    <div class="my-4 flex justify-center">
                        <div class="flex space-x-2">
                            <input type="password" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold rounded-lg border focus:outline-none focus:ring-2" style="background-color: var(--surface); border-color: var(--border); --tw-ring-color: var(--primary-accent);" readonly>
                            <input type="password" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold rounded-lg border focus:outline-none focus:ring-2" style="background-color: var(--surface); border-color: var(--border); --tw-ring-color: var(--primary-accent);" readonly>
                            <input type="password" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold rounded-lg border focus:outline-none focus:ring-2" style="background-color: var(--surface); border-color: var(--border); --tw-ring-color: var(--primary-accent);" readonly>
                            <input type="password" maxlength="1" class="w-12 h-12 text-center text-2xl font-bold rounded-lg border focus:outline-none focus:ring-2" style="background-color: var(--surface); border-color: var(--border); --tw-ring-color: var(--primary-accent);" readonly>
                        </div>
                    </div>
                </div>
            </main>
            <div class="pb-4">
                <form id="addMoneyForm" method="post" action="paystack_handler.php">
                    <input type="hidden" name="amount" id="form_amount_sv">
                    <input type="hidden" name="transaction_pin" id="form_pin">
                    <button type="submit" id="confirmBtn" class="w-full py-4 rounded-full text-lg font-medium mb-6 transition-colors duration-300" style="background-color: var(--primary-accent); color: white;" disabled>
                        Confirm
                    </button>
                </form>
                <div class="numpad-grid">
                    <button type="button" class="p-4 rounded-lg numpad-button">1</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">2</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">3</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">4</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">5</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">6</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">7</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">8</button>
                    <button type="button" class="p-4 rounded-lg numpad-button">9</button>
                    <div></div>
                    <button type="button" class="p-4 rounded-lg numpad-button">0</button>
                    <button type="button" id="pinBackspaceBtn" class="text-red-500 p-4 rounded-lg numpad-button"><span class="material-icons">backspace</span></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Theme application logic
    document.addEventListener('DOMContentLoaded', () => {
        const htmlElement = document.documentElement;

        const applyTheme = (theme) => {
            htmlElement.setAttribute('data-theme', theme);
        };

        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme) {
            applyTheme(savedTheme);
        } else if (prefersDark) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }

        // Rest of your existing DOMContentLoaded logic
        const amountStep = document.getElementById('amountStep');
        const pinStep = document.getElementById('pinStep');
        const goToPinStepBtn = document.getElementById('goToPinStepBtn');
        const backToAmountStepBtn = document.getElementById('backToAmountStepBtn');
        const amountDisplay = document.getElementById('amountDisplay');
        const nairaEquivalent = document.getElementById('nairaEquivalent');
        const amountNumpad = amountStep.querySelector('.numpad-grid');
        const amountBackspaceBtn = document.getElementById('amountBackspaceBtn');
        const pinInputs = pinStep.querySelectorAll('input[type="password"]');
        const pinNumpad = pinStep.querySelector('.numpad-grid');
        const pinBackspaceBtn = document.getElementById('pinBackspaceBtn');
        const confirmBtn = document.getElementById('confirmBtn');
        const formAmountInput = document.getElementById('form_amount_sv');
        const formPinInput = document.getElementById('form_pin');
        const confirmAmountText = document.getElementById('confirmAmountText');
        const confirmNairaEquivalent = document.getElementById('confirmNairaEquivalent');
        const totalPayAmountText = document.getElementById('totalPayAmountText');

        let currentAmount = "0";
        let currentPin = "";
        const SV_TO_NAIRA_RATE = 100;

        const updateAmountDisplay = () => {
            amountDisplay.textContent = parseFloat(currentAmount).toLocaleString('en-US');
            const nairaValue = parseFloat(currentAmount) * SV_TO_NAIRA_RATE;
            nairaEquivalent.textContent = `₦${nairaValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            goToPinStepBtn.disabled = parseFloat(currentAmount) <= 0;
        };

        const updatePinDisplay = () => {
            pinInputs.forEach((input, index) => {
                input.value = currentPin[index] || '';
            });
            confirmBtn.disabled = currentPin.length !== 4;
        };

        amountNumpad.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') return;
            const key = e.target.textContent;

            if (key >= '0' && key <= '9') {
                if (currentAmount === "0") {
                    currentAmount = key;
                } else {
                    currentAmount += key;
                }
            } else if (key === '.' && !currentAmount.includes('.')) {
                currentAmount += '.';
            }
            updateAmountDisplay();
        });

        amountBackspaceBtn.addEventListener('click', () => {
            if (currentAmount.length > 1) {
                currentAmount = currentAmount.slice(0, -1);
            } else {
                currentAmount = "0";
            }
            updateAmountDisplay();
        });

        pinNumpad.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') return;
            const key = e.target.textContent;

            if (key >= '0' && key <= '9' && currentPin.length < 4) {
                currentPin += key;
                updatePinDisplay();
            }
        });

        pinBackspaceBtn.addEventListener('click', () => {
            if (currentPin.length > 0) {
                currentPin = currentPin.slice(0, -1);
                updatePinDisplay();
            }
        });

        goToPinStepBtn.addEventListener('click', () => {
            amountStep.style.display = 'none';
            pinStep.style.display = 'flex';
            formAmountInput.value = currentAmount;
            confirmAmountText.textContent = `SV ${parseFloat(currentAmount).toLocaleString()}`;
            const nairaValueForDeposit = parseFloat(currentAmount) * SV_TO_NAIRA_RATE;
            confirmNairaEquivalent.textContent = `₦${nairaValueForDeposit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (Deposit Value)`;
            totalPayAmountText.textContent = 'Calculating...'; // Placeholder until actual calculation is received
        });

        backToAmountStepBtn.addEventListener('click', () => {
            pinStep.style.display = 'none';
            amountStep.style.display = 'flex';
            currentPin = "";
            updatePinDisplay();
            totalPayAmountText.textContent = ''; // Clear total pay amount when going back
        });
        
        confirmBtn.addEventListener('click', async (e) => {
            e.preventDefault(); 
            if (currentPin.length !== 4) return;

            // Show loading state
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner"></span> Processing...';

            formAmountInput.value = currentAmount;
            formPinInput.value = currentPin;

            const formData = new FormData(document.getElementById('addMoneyForm'));

            try {
                const response = await fetch('paystack_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'error') {
                    const transactionLimitExceededMessage = 'Transaction limit of SV200 exceeded. Contact a broker to fund your wallet.';
                    const delay = (data.message === transactionLimitExceededMessage) ? 20000 : 0; // 20 seconds delay for specific error, 0 for others

                    setTimeout(() => {
                        showPopup(data.message);
                        // Redirect back to amount step
                        pinStep.style.display = 'none';
                        amountStep.style.display = 'flex';
                        currentPin = "";
                        updatePinDisplay();
                        totalPayAmountText.textContent = ''; // Clear total pay amount on error
                        // Reset confirm button
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = 'Confirm';
                    }, delay);
                } else if (data.status === 'success' && data.paystack_data) {
                    const finalNairaAmount = data.paystack_data.final_naira_amount;
                    totalPayAmountText.textContent = `₦${finalNairaAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

                    // Initialize and open Paystack inline script
                    const handler = PaystackPop.setup({
                        key: data.paystack_data.key,
                        email: data.paystack_data.email,
                        amount: data.paystack_data.amount,
                        currency: data.paystack_data.currency,
                        ref: data.paystack_data.ref,
                        callback: function(response) {
                            window.location = data.paystack_data.callback_url + '?reference=' + response.reference;
                        },
                        onClose: function() {
                            showPopup('Payment was not completed.');
                            // Reset confirm button
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = 'Confirm';
                        }
                    });
                    handler.openIframe();
                }
            } catch (error) {
                console.error('Error:', error);
                setTimeout(() => {
                    showPopup('An unexpected error occurred. Please try again.');
                    pinStep.style.display = 'none';
                    amountStep.style.display = 'flex';
                    currentPin = "";
                    updatePinDisplay();
                    totalPayAmountText.textContent = ''; // Clear total pay amount on error
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirm';
                }, 0); // No delay for unexpected errors
            }
        });

        // Popup functions
        const customPopup = document.getElementById('customPopup');
        const popupMessageText = document.getElementById('popupMessageText');
        const popupCloseBtn = document.getElementById('popupCloseBtn');

        function showPopup(message) {
            popupMessageText.textContent = message;
            customPopup.classList.add('show');
        }

        function hidePopup() {
            customPopup.classList.remove('show');
        }

        popupCloseBtn.addEventListener('click', hidePopup);

        updateAmountDisplay();
        updatePinDisplay();
    });
    </script>
  </body>
</html>
