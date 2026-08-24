@extends('devotee.layouts.app')

@section('title', 'Book Pooja - Wizard')

@section('page-css')
<style>
    /* Wizard progress indicators */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
        background: white;
        border-radius: 20px;
        padding: 20px 30px;
        border: 1px solid rgba(184, 134, 58, 0.08);
    }
    .wizard-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
        z-index: 2;
    }
    .wizard-step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f0ece6;
        color: #7b6b5a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 8px;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .wizard-step.active .wizard-step-circle {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        box-shadow: 0 4px 12px rgba(184, 134, 58, 0.3);
        border-color: #ffffff;
    }
    .wizard-step.completed .wizard-step-circle {
        background: #1f9d6a;
        color: white;
        box-shadow: 0 4px 12px rgba(31, 157, 106, 0.2);
    }
    .wizard-step-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #7b6b5a;
        transition: all 0.3s;
        text-align: center;
    }
    .wizard-step.active .wizard-step-label { color: #b8863a; }
    .wizard-step.completed .wizard-step-label { color: #1f9d6a; }

    .wizard-progress {
        position: absolute;
        top: 40px;
        left: 8%;
        right: 8%;
        height: 3px;
        background: #f0ece6;
        z-index: 1;
    }
    .wizard-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #b8863a, #1f9d6a);
        width: 0%;
        transition: width 0.4s ease;
    }

    /* Cards */
    .pooja-card {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.01);
        overflow: hidden;
        transition: all 0.3s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .pooja-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(184, 134, 58, 0.08);
        border-color: #b8863a;
    }
    .pooja-card-header {
        background: linear-gradient(135deg, #b8863a0a, #d4a05a0a);
        padding: 20px;
        border-bottom: 1px solid #fcfaf7;
    }
    .pooja-card-title {
        font-weight: 700;
        color: #2d1f0e;
        margin: 0;
        font-size: 1.1rem;
    }
    .pooja-card-body {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .filter-section {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        padding: 20px;
        margin-bottom: 24px;
    }
    .category-tab {
        background: #f5f0ea;
        color: #7b6b5a;
        border: none;
        border-radius: 40px;
        padding: 6px 18px;
        font-weight: 600;
        font-size: 0.85rem;
        margin-right: 6px;
        margin-bottom: 6px;
        transition: all 0.2s;
    }
    .category-tab.active {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        box-shadow: 0 4px 10px rgba(184, 134, 58, 0.2);
    }

    .selected-panel {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.08);
        padding: 24px;
        position: sticky;
        top: 100px;
    }
    .selected-panel h5 {
        font-weight: 700;
        color: #2d1f0e;
        border-bottom: 2px solid #b8863a;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .validation-msg {
        color: #dc3545;
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 5px;
        display: none;
    }

    .config-card {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(184, 134, 58, 0.08);
        padding: 24px;
        margin-bottom: 20px;
    }
    .config-card-title {
        font-weight: 700;
        font-size: 1.1rem;
        color: #2d1f0e;
        margin-bottom: 16px;
        border-bottom: 1px solid #f0ece6;
        padding-bottom: 8px;
    }
    .badge-num {
        background: #b8863a;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        margin-right: 6px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <!-- Draft Booking Alert -->
    <div id="draftAlert" class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4 d-none" role="alert">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Unfinished booking detected!</strong> You have an unfinished booking draft. Continue where you left off?
            </div>
            <div>
                <button type="button" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold me-2" onclick="restoreDraft()">Yes, Continue</button>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="dismissDraft()">No, Start Fresh</button>
            </div>
        </div>
    </div>

    <!-- 6 Step Wizard Indicators -->
    <div class="wizard-steps">
        <div class="wizard-progress">
            <div class="wizard-progress-bar" id="wizardProgressBar"></div>
        </div>
        <div class="wizard-step active" id="stepIndicator1">
            <div class="wizard-step-circle">1</div>
            <div class="wizard-step-label">Select Poojas</div>
        </div>
        <div class="wizard-step" id="stepIndicator2">
            <div class="wizard-step-circle">2</div>
            <div class="wizard-step-label">Date & Time</div>
        </div>
        <div class="wizard-step" id="stepIndicator3">
            <div class="wizard-step-circle">3</div>
            <div class="wizard-step-label">Priest Selection</div>
        </div>
        <div class="wizard-step" id="stepIndicator4">
            <div class="wizard-step-circle">4</div>
            <div class="wizard-step-label">Online/Offline</div>
        </div>
        <div class="wizard-step" id="stepIndicator5">
            <div class="wizard-step-circle">5</div>
            <div class="wizard-step-label">Summary</div>
        </div>
        <div class="wizard-step" id="stepIndicator6">
            <div class="wizard-step-circle">6</div>
            <div class="wizard-step-label">Payment</div>
        </div>
    </div>

    <form id="bookingWizardForm" method="POST" action="{{ route('devotee.book-pooja.post') }}">
        @csrf

        <!-- STEP 1: SELECT POOJAS -->
        <div class="wizard-content-step" id="stepContent1">
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="fw-bold mb-2" style="color: #7b6b5a;">Filter by Category</h6>
                        <button type="button" class="category-tab active" onclick="filterCategory('All')">All</button>
                        <button type="button" class="category-tab" onclick="filterCategory('Daily')">Daily</button>
                        <button type="button" class="category-tab" onclick="filterCategory('Special')">Special</option>
                        <button type="button" class="category-tab" onclick="filterCategory('General')">General</option>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-sm-0">
                        <label class="form-label fw-bold small text-muted">Search Pooja</label>
                        <input type="text" id="searchInput" class="form-control rounded-pill px-3" placeholder="Pooja name..." onkeyup="searchPoojas()">
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label fw-bold small text-muted">Sort By</label>
                        <select id="sortSelect" class="form-select rounded-pill px-3" onchange="sortPoojas()">
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="price_asc">Price (Low to High)</option>
                            <option value="price_desc">Price (High to Low)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="row g-4" id="poojaGrid">
                        @foreach($poojas as $pooja)
                        <div class="col-md-6 pooja-card-wrapper" 
                             data-category="{{ $pooja->category }}" 
                             data-name="{{ strtolower($pooja->pooja_name) }}"
                             data-price="{{ $pooja->pooja_fee }}">
                            <div class="pooja-card">
                                <div class="pooja-card-header">
                                    <h5 class="pooja-card-title">{{ $pooja->pooja_name }}</h5>
                                </div>
                                <div class="pooja-card-body">
                                    <p class="text-muted small mb-3">{{ Str::limit($pooja->description, 100) }}</p>
                                    <div class="pooja-price-section d-flex justify-content-between align-items-center border-top pt-3">
                                        <div class="fw-bold text-warning fs-5">₹{{ number_format($pooja->pooja_fee) }}</div>
                                        <button type="button" 
                                                id="selectBtn_{{ $pooja->pooja_id }}"
                                                class="btn btn-outline-warning btn-select-pooja" 
                                                onclick="togglePoojaSelection({{ json_encode($pooja) }})">
                                            Select
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel">
                        <h5>Selected Poojas</h5>
                        <div id="selectedPoojasList" class="mb-4 text-center text-muted small py-3">
                            No rituals selected. Select from list.
                        </div>
                        <div id="step1Validation" class="validation-msg mb-3">Please select at least one pooja.</div>
                        <hr>
                        <button type="button" id="nextBtnStep1" class="btn btn-warning w-100 rounded-pill fw-bold" onclick="validateAndGoToStep(2)">
                            Next: Date & Time <i class="bi bi-arrow-right-short"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: SELECT DATE & TIME -->
        <div class="wizard-content-step d-none" id="stepContent2">
            <div class="row g-4">
                <div class="col-lg-8" id="step2ConfigContainer">
                    <!-- Dynamic Date/Time selects injected here -->
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel">
                        <h5>Select Schedule</h5>
                        <p class="text-muted small">Choose booking date and timeslot for each selected pooja.</p>
                        <div id="step2Validation" class="validation-msg mb-3">Please select booking date and time slot for all poojas.</div>
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill fw-bold" onclick="navigateToStep(1)">Back</button>
                            <button type="button" id="nextBtnStep2" class="btn btn-warning w-50 rounded-pill fw-bold" onclick="validateAndGoToStep(3)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 3: PRIEST SELECTION -->
        <div class="wizard-content-step d-none" id="stepContent3">
            <div class="row g-4">
                <div class="col-lg-8" id="step3ConfigContainer">
                    <!-- Dynamic Priest selects injected here -->
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel">
                        <h5>Priest Assignment</h5>
                        <p class="text-muted small">Choose auto-assignment or choose a specific priest for your rituals.</p>
                        <div id="step3Validation" class="validation-msg mb-3">Please select priest preference for all poojas.</div>
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill fw-bold" onclick="navigateToStep(2)">Back</button>
                            <button type="button" id="nextBtnStep3" class="btn btn-warning w-50 rounded-pill fw-bold" onclick="validateAndGoToStep(4)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 4: ONLINE / OFFLINE SELECTION -->
        <div class="wizard-content-step d-none" id="stepContent4">
            <div class="row g-4">
                <div class="col-lg-8" id="step4ConfigContainer">
                    <!-- Dynamic Online/Offline selects injected here -->
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel">
                        <h5>Pooja Mode</h5>
                        <p class="text-muted small">Select if you will visit the temple or want the pooja performed online (with prasadam shipping).</p>
                        <div id="step4Validation" class="validation-msg mb-3">Please select online or offline mode and enter shipping address.</div>
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill fw-bold" onclick="navigateToStep(3)">Back</button>
                            <button type="button" id="nextBtnStep4" class="btn btn-warning w-50 rounded-pill fw-bold" onclick="validateAndGoToStep(5)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 5: BOOKING SUMMARY -->
        <div class="wizard-content-step d-none" id="stepContent5">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-card-checklist text-warning"></i> Booking Summary</h5>
                        <div id="step5SummaryContainer">
                            <!-- Injected Summary items -->
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel">
                        <h5>Price Breakdown</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="summaryBasePrice">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount</span>
                            <span id="summaryDiscount">-₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Prasadam Shipping</span>
                            <span id="summaryShipping">₹0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold mb-4 fs-5">
                            <span>Total Price</span>
                            <span id="summaryTotalPrice">₹0.00</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill fw-bold" onclick="navigateToStep(4)">Back</button>
                            <button type="button" class="btn btn-warning w-50 rounded-pill fw-bold" onclick="navigateToStep(6)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 6: PAYMENT -->
        <div class="wizard-content-step d-none" id="stepContent6">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: white;">
                        <h5 class="fw-bold mb-4"><i class="bi bi-wallet2 text-warning"></i> Choose Payment Method</h5>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="payment_method" id="payUPI" value="UPI" checked onchange="saveDraftState()">
                                <label class="btn btn-outline-warning w-100 py-3 rounded-4 fw-bold" for="payUPI">
                                    <i class="bi bi-qr-code fs-4 d-block mb-1"></i> UPI QR Code
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="payment_method" id="payRazorpay" value="Razorpay" onchange="saveDraftState()">
                                <label class="btn btn-outline-warning w-100 py-3 rounded-4 fw-bold" for="payRazorpay">
                                    <i class="bi bi-credit-card fs-4 d-block mb-1"></i> Card / NetBanking
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="payment_method" id="payCash" value="Cash" onchange="saveDraftState()">
                                <label class="btn btn-outline-warning w-100 py-3 rounded-4 fw-bold" for="payCash">
                                    <i class="bi bi-cash-stack fs-4 d-block mb-1"></i> Cash at Temple
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <input type="radio" class="btn-check" name="payment_method" id="payCounter" value="Counter" onchange="saveDraftState()">
                                <label class="btn btn-outline-warning w-100 py-3 rounded-4 fw-bold" for="payCounter">
                                    <i class="bi bi-shop fs-4 d-block mb-1"></i> Office Counter
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="selected-panel text-center">
                        <h5 class="mb-3">Complete Booking</h5>
                        <p class="text-muted small mb-4">Click below to finalize your booking records. You will receive notification confirmation updates.</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill fw-bold" onclick="navigateToStep(5)">Back</button>
                            <button type="submit" class="btn btn-success w-50 rounded-pill fw-bold" onclick="clearDraftState()">Book Now</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('page-js')
<script>
    // State storage variables
    let allSelectedPoojas = [];
    let membershipDiscountPercent = {{ $membership->discount_percentage ?? 0 }};
    let devoteeMembershipTier = "{{ $membership->membership_name ?? 'None' }}";
    
    // Check if draft exists on load
    $(document).ready(function() {
        if (localStorage.getItem('devotee_booking_draft')) {
            $('#draftAlert').removeClass('d-none');
        }
        updateSelectedPoojasUI();
    });

    // 1. SELECT POOJA ACTIONS
    function togglePoojaSelection(pooja) {
        const index = allSelectedPoojas.findIndex(p => p.pooja_id === pooja.pooja_id);
        const btn = document.getElementById(`selectBtn_${pooja.pooja_id}`);
        
        if (index > -1) {
            allSelectedPoojas.splice(index, 1);
            btn.classList.remove('selected', 'btn-success');
            btn.classList.add('btn-outline-warning');
            btn.innerHTML = 'Select';
        } else {
            // Check if pooja exists with date/time properties initialized
            pooja.booking_date = '';
            pooja.booking_time = '';
            pooja.booking_type = 'Offline';
            pooja.delivery_address = '';
            pooja.priest_option = 'auto';
            pooja.preferred_priest_id = '';
            allSelectedPoojas.push(pooja);
            btn.classList.add('selected', 'btn-success');
            btn.classList.remove('btn-outline-warning');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Selected';
        }
        
        updateSelectedPoojasUI();
        saveDraftState();
    }

    function updateSelectedPoojasUI() {
        const listDiv = document.getElementById('selectedPoojasList');
        if (allSelectedPoojas.length === 0) {
            listDiv.innerHTML = 'No rituals selected. Select from list.';
            return;
        }

        let html = '<ul class="list-group list-group-flush text-start">';
        allSelectedPoojas.forEach((p, idx) => {
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center py-2 bg-transparent">
                    <div>
                        <strong class="text-dark">${p.pooja_name}</strong>
                        <div class="small text-muted">₹${Number(p.pooja_fee).toLocaleString()}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" onclick='removePooja(${idx}, ${p.pooja_id})'>
                        <i class="bi bi-trash"></i>
                    </button>
                </li>
            `;
        });
        html += '</ul>';
        listDiv.innerHTML = html;
        $('#step1Validation').hide();
    }

    function removePooja(index, id) {
        allSelectedPoojas.splice(index, 1);
        const btn = document.getElementById(`selectBtn_${id}`);
        if(btn) {
            btn.classList.remove('selected', 'btn-success');
            btn.classList.add('btn-outline-warning');
            btn.innerHTML = 'Select';
        }
        updateSelectedPoojasUI();
        saveDraftState();
    }

    // SEARCH & FILTER ACTIONS
    function filterCategory(category) {
        document.querySelectorAll('.category-tab').forEach(t => {
            t.classList.remove('active');
            if (t.innerText === category || (category === 'All' && t.innerText === 'All')) {
                t.classList.add('active');
            }
        });
        document.querySelectorAll('.pooja-card-wrapper').forEach(card => {
            const cat = card.getAttribute('data-category');
            if (category === 'All' || cat === category) card.style.display = 'block';
            else card.style.display = 'none';
        });
    }

    function searchPoojas() {
        const q = $('#searchInput').val().toLowerCase();
        document.querySelectorAll('.pooja-card-wrapper').forEach(card => {
            const name = card.getAttribute('data-name');
            if (name.includes(q)) card.style.display = 'block';
            else card.style.display = 'none';
        });
    }

    function sortPoojas() {
        const criterion = $('#sortSelect').val();
        const grid = document.getElementById('poojaGrid');
        const cards = Array.from(grid.children);

        cards.sort((a, b) => {
            const priceA = parseFloat(a.getAttribute('data-price'));
            const priceB = parseFloat(b.getAttribute('data-price'));
            const nameA = a.getAttribute('data-name');
            const nameB = b.getAttribute('data-name');
            if (criterion === 'price_asc') return priceA - priceB;
            if (criterion === 'price_desc') return priceB - priceA;
            return nameA.localeCompare(nameB);
        });

        grid.innerHTML = '';
        cards.forEach(card => grid.appendChild(card));
    }

    // STEP NAV & VALIDATION
    function navigateToStep(step) {
        document.querySelectorAll('.wizard-content-step').forEach(c => c.classList.add('d-none'));
        $(`#stepContent${step}`).removeClass('d-none');

        for (let i = 1; i <= 6; i++) {
            const ind = document.getElementById(`stepIndicator${i}`);
            ind.classList.remove('active', 'completed');
            if (i < step) ind.classList.add('completed');
            else if (i === step) ind.classList.add('active');
        }

        const width = ((step - 1) / 5) * 85;
        document.getElementById('wizardProgressBar').style.width = `${width}%`;

        // Render dynamic forms based on step
        if (step === 2) buildStep2Form();
        if (step === 3) buildStep3Form();
        if (step === 4) buildStep4Form();
        if (step === 5) buildStep5Summary();

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function validateAndGoToStep(step) {
        $('.validation-msg').hide();

        if (step === 2) {
            if (allSelectedPoojas.length === 0) {
                $('#step1Validation').show();
                return;
            }
            navigateToStep(2);
        }

        if (step === 3) {
            let valid = true;
            allSelectedPoojas.forEach((p, idx) => {
                const date = $(`#booking_date_${idx}`).val();
                const time = $(`#booking_time_${idx}`).val();
                p.booking_date = date;
                p.booking_time = time;
                if (!date || !time) valid = false;
            });
            if (!valid) {
                $('#step2Validation').show();
                return;
            }
            navigateToStep(3);
        }

        if (step === 4) {
            let valid = true;
            allSelectedPoojas.forEach((p, idx) => {
                const opt = $(`#priest_option_${idx}`).val();
                const pId = $(`#preferred_priest_${idx}`).val();
                p.priest_option = opt;
                p.preferred_priest_id = pId;
                if (opt === 'preferred' && !pId) valid = false;
            });
            if (!valid) {
                $('#step3Validation').show();
                return;
            }
            navigateToStep(4);
        }

        if (step === 5) {
            let valid = true;
            allSelectedPoojas.forEach((p, idx) => {
                const type = $(`#booking_type_${idx}`).val();
                const addr = $(`#delivery_address_${idx}`).val();
                p.booking_type = type;
                p.delivery_address = addr;
                if (!type) valid = false;
                if (type === 'Online' && !addr) valid = false;
            });
            if (!valid) {
                $('#step4Validation').show();
                return;
            }
            navigateToStep(5);
        }
    }

    // BUILD DYNAMIC FIELDS
    function buildStep2Form() {
        const container = document.getElementById('step2ConfigContainer');
        let html = '';
        allSelectedPoojas.forEach((p, idx) => {
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            const maxDate = new Date();
            maxDate.setDate(today.getDate() + 10);
            const maxDateStr = maxDate.toISOString().split('T')[0];
            html += `
                <div class="config-card">
                    <div class="config-card-title"><span class="badge-num">${idx + 1}</span> ${p.pooja_name} - Select Date & Time</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Booking Date</label>
                            <input type="date" id="booking_date_${idx}" class="form-control rounded-3" min="${todayStr}" max="${maxDateStr}" value="${p.booking_date || ''}" onchange="saveDraftState()" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Time Slot</label>
                            <select id="booking_time_${idx}" class="form-select rounded-3" onchange="saveDraftState()" required>
                                <option value="">Select timeslot</option>
                                <option value="09:00:00" ${p.booking_time === '09:00:00' ? 'selected' : ''}>09:00 AM</option>
                                <option value="10:00:00" ${p.booking_time === '10:00:00' ? 'selected' : ''}>10:00 AM</option>
                                <option value="11:00:00" ${p.booking_time === '11:00:00' ? 'selected' : ''}>11:00 AM</option>
                                <option value="12:00:00" ${p.booking_time === '12:00:00' ? 'selected' : ''}>12:00 PM</option>
                                <option value="14:00:00" ${p.booking_time === '14:00:00' ? 'selected' : ''}>02:00 PM</option>
                                <option value="15:00:00" ${p.booking_time === '15:00:00' ? 'selected' : ''}>03:00 PM</option>
                                <option value="16:00:00" ${p.booking_time === '16:00:00' ? 'selected' : ''}>04:00 PM</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function buildStep3Form() {
        const container = document.getElementById('step3ConfigContainer');
        let html = '';
        allSelectedPoojas.forEach((p, idx) => {
            const showPriestOptions = (devoteeMembershipTier === 'Gold' || devoteeMembershipTier === 'Platinum') ? 'block' : 'none';
            html += `
                <div class="config-card">
                    <div class="config-card-title"><span class="badge-num">${idx + 1}</span> ${p.pooja_name} - Priest Option</div>
                    
                    <div class="mb-3" style="display: ${showPriestOptions}">
                        <label class="form-label fw-semibold">Assigning Method</label>
                        <select id="priest_option_${idx}" class="form-select rounded-3" onchange="togglePriestField(${idx}); saveDraftState();" required>
                            <option value="auto" ${p.priest_option === 'auto' ? 'selected' : ''}>Auto Workload-Balanced Assignment</option>
                            <option value="preferred" ${p.priest_option === 'preferred' ? 'selected' : ''}>Select Specific Priest</option>
                        </select>
                    </div>

                    <div class="mb-3" id="priest_select_wrapper_${idx}" style="display: ${p.priest_option === 'preferred' ? 'block' : 'none'}">
                        <label class="form-label fw-semibold">Select Priest</label>
                        <select id="preferred_priest_${idx}" class="form-select rounded-3" onchange="saveDraftState()">
                            <option value="">Select Priest</option>
                        </select>
                    </div>
                </div>
            `;
            
            // Auto fetch available priests via AJAX if timeslots exist
            if(p.booking_date && p.booking_time) {
                fetch(`{{ url('/booking/check-availability') }}?pooja_id=${p.pooja_id}&booking_date=${p.booking_date}&booking_time=${p.booking_time}`)
                    .then(res => res.json())
                    .then(data => {
                        const select = document.getElementById(`preferred_priest_${idx}`);
                        if(select) {
                            select.innerHTML = '<option value="">Select Priest</option>';
                            data.priests.forEach(pr => {
                                select.innerHTML += `<option value="${pr.priest_id}" ${p.preferred_priest_id == pr.priest_id ? 'selected' : ''}>${pr.name} (Exp: ${pr.experience} Yrs, Workload: ${pr.workload})</option>`;
                            });
                        }
                    });
            }
        });
        container.innerHTML = html;
    }

    function togglePriestField(idx) {
        const val = $(`#priest_option_${idx}`).val();
        if(val === 'preferred') {
            $(`#priest_select_wrapper_${idx}`).show();
            $(`#preferred_priest_${idx}`).prop('required', true);
        } else {
            $(`#priest_select_wrapper_${idx}`).hide();
            $(`#preferred_priest_${idx}`).prop('required', false).val('');
        }
    }

    function buildStep4Form() {
        const container = document.getElementById('step4ConfigContainer');
        let html = '';
        allSelectedPoojas.forEach((p, idx) => {
            html += `
                <div class="config-card">
                    <div class="config-card-title"><span class="badge-num">${idx + 1}</span> ${p.pooja_name} - Select Mode</div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Mode Option</label>
                            <select id="booking_type_${idx}" class="form-select rounded-3" onchange="toggleAddress(${idx}); saveDraftState();" required>
                                <option value="Offline" ${p.booking_type === 'Offline' ? 'selected' : ''}>Temple Visit (Offline)</option>
                                ${p.online_allowed ? `<option value="Online" ${p.booking_type === 'Online' ? 'selected' : ''}>Online Pooja (+ ₹200 Shipping)</option>` : ''}
                            </select>
                        </div>
                        <div class="col-12" id="address_wrapper_${idx}" style="display: ${p.booking_type === 'Online' ? 'block' : 'none'}">
                            <label class="form-label fw-semibold required-field">Prasadam Shipping Address</label>
                            <textarea id="delivery_address_${idx}" class="form-control rounded-3" rows="3" placeholder="Enter delivery address..." onkeyup="saveDraftState()">${p.delivery_address || ''}</textarea>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function toggleAddress(idx) {
        const val = $(`#booking_type_${idx}`).val();
        if(val === 'Online') {
            $(`#address_wrapper_${idx}`).show();
            $(`#delivery_address_${idx}`).prop('required', true);
        } else {
            $(`#address_wrapper_${idx}`).hide();
            $(`#delivery_address_${idx}`).prop('required', false).val('');
        }
    }

    function buildStep5Summary() {
        const container = document.getElementById('step5SummaryContainer');
        let html = '<ul class="list-group list-group-flush">';
        let totalBase = 0;
        let totalDiscount = 0;
        let totalShipping = 0;

        allSelectedPoojas.forEach((p, idx) => {
            const base = parseFloat(p.pooja_fee);
            const disc = Math.round(((base * membershipDiscountPercent) / 100) * 100) / 100;
            const ship = p.booking_type === 'Online' ? 200.00 : 0.00;

            totalBase += base;
            totalDiscount += disc;
            totalShipping += ship;

            html += `
                <li class="list-group-item bg-transparent py-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong class="text-dark">${p.pooja_name}</strong>
                            <div class="small text-muted mt-1"><i class="bi bi-calendar"></i> ${p.booking_date} at ${p.booking_time}</div>
                            <div class="small text-muted"><i class="bi bi-geo-alt"></i> Mode: ${p.booking_type} | Priest Option: ${p.priest_option}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">₹${base.toLocaleString()}</div>
                            ${disc > 0 ? `<div class="small text-success">-₹${disc.toLocaleString()}</div>` : ''}
                            ${ship > 0 ? `<div class="small text-info">+₹${ship.toLocaleString()}</div>` : ''}
                        </div>
                    </div>
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;

        // Pricing Breakdown Updates
        const total = totalBase - totalDiscount + totalShipping;
        $('#summaryBasePrice').text(`₹${totalBase.toLocaleString()}`);
        $('#summaryDiscount').text(`-₹${totalDiscount.toLocaleString()}`);
        $('#summaryShipping').text(`₹${totalShipping.toLocaleString()}`);
        $('#summaryTotalPrice').text(`₹${total.toLocaleString()}`);

        // Inject fields to form input for submission
        injectFormInputs();
    }

    function injectFormInputs() {
        // Clear old hidden inputs inside form
        $('#bookingWizardForm .hidden-form-inputs').remove();
        
        let inputsHtml = '<div class="hidden-form-inputs">';
        allSelectedPoojas.forEach((p, idx) => {
            inputsHtml += `
                <input type="hidden" name="bookings[${idx}][pooja_id]" value="${p.pooja_id}">
                <input type="hidden" name="bookings[${idx}][booking_date]" value="${p.booking_date}">
                <input type="hidden" name="bookings[${idx}][booking_time]" value="${p.booking_time}">
                <input type="hidden" name="bookings[${idx}][booking_type]" value="${p.booking_type}">
                <input type="hidden" name="bookings[${idx}][delivery_address]" value="${p.delivery_address || ''}">
                <input type="hidden" name="bookings[${idx}][priest_option]" value="${p.priest_option}">
                <input type="hidden" name="bookings[${idx}][preferred_priest_id]" value="${p.preferred_priest_id || ''}">
            `;
        });
        inputsHtml += '</div>';
        $('#bookingWizardForm').append(inputsHtml);
    }

    // LOCAL STORAGE DRAFT SAVING/RECOVERY
    function saveDraftState() {
        const state = {
            poojas: allSelectedPoojas,
            payment: $('input[name="payment_method"]:checked').val()
        };
        localStorage.setItem('devotee_booking_draft', JSON.stringify(state));
    }

    function clearDraftState() {
        localStorage.removeItem('devotee_booking_draft');
    }

    function restoreDraft() {
        try {
            const raw = localStorage.getItem('devotee_booking_draft');
            if (raw) {
                const state = JSON.parse(raw);
                allSelectedPoojas = state.poojas || [];
                
                // Check radio select
                if (state.payment) {
                    $(`input[name="payment_method"][value="${state.payment}"]`).prop('checked', true);
                }

                // Toggle select classes on step 1 cards
                allSelectedPoojas.forEach(p => {
                    const btn = document.getElementById(`selectBtn_${p.pooja_id}`);
                    if (btn) {
                        btn.classList.add('selected', 'btn-success');
                        btn.classList.remove('btn-outline-warning');
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Selected';
                    }
                });

                updateSelectedPoojasUI();
                $('#draftAlert').addClass('d-none');
                
                // Automatically go to step 2 to continue configuration
                navigateToStep(2);
            }
        } catch(e) {
            console.error('Failed to restore draft', e);
        }
    }

    function dismissDraft() {
        clearDraftState();
        $('#draftAlert').addClass('d-none');
    }
</script>
@endsection