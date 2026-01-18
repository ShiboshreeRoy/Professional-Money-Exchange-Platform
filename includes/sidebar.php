<!-- Professional Sidebar -->
<div class="sidebar">
    <h4 class="mb-4 text-primary">
        <i class="fas fa-newspaper me-2"></i>Latest Updates
    </h4>
    
    <!-- Featured Items -->
    <?php foreach($slides as $index => $slide): ?>
    <div class="sidebar-item">
        <img src="<?php echo $slide['image']; ?>" alt="<?php echo $slide['title']; ?>" class="sidebar-img">
        <h6 class="fw-bold"><?php echo $slide['title']; ?></h6>
        <p class="small text-muted"><?php echo $slide['description']; ?></p>
        <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
    </div>
    <?php endforeach; ?>
    
    <!-- Categories Section -->
    <div class="mt-4 pt-3 border-top">
        <h5 class="mb-3">
            <i class="fas fa-tags me-2 text-secondary"></i>Categories
        </h5>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary">Technology</span>
            <span class="badge bg-success">Business</span>
            <span class="badge bg-warning text-dark">Innovation</span>
            <span class="badge bg-info">Design</span>
            <span class="badge bg-danger">Development</span>
            <span class="badge bg-secondary">Marketing</span>
        </div>
    </div>
    
    <!-- Newsletter Signup -->
    <div class="mt-4 p-3 bg-light rounded">
        <h5 class="mb-3">
            <i class="fas fa-envelope me-2 text-primary"></i>Newsletter
        </h5>
        <p class="small text-muted">Subscribe to get latest updates and news</p>
        <form>
            <div class="mb-2">
                <input type="email" class="form-control form-control-sm" placeholder="Your email address">
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">
                Subscribe Now
            </button>
        </form>
    </div>
    
    <!-- Social Media Links -->
    <div class="mt-4 pt-3 border-top">
        <h5 class="mb-3">
            <i class="fas fa-share-alt me-2 text-secondary"></i>Follow Us
        </h5>
        <div class="d-flex gap-2">
            <a href="#" class="btn btn-primary btn-sm rounded-circle">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="btn btn-info btn-sm rounded-circle">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="btn btn-danger btn-sm rounded-circle">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="btn btn-primary btn-sm rounded-circle">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="mt-4 pt-3 border-top">
        <h5 class="mb-3">
            <i class="fas fa-chart-bar me-2 text-success"></i>Quick Stats
        </h5>
        <div class="row text-center">
            <div class="col-6 mb-2">
                <div class="p-2 bg-primary bg-opacity-10 rounded">
                    <div class="h5 mb-0 text-primary">1.2K+</div>
                    <small class="text-muted">Projects</small>
                </div>
            </div>
            <div class="col-6 mb-2">
                <div class="p-2 bg-success bg-opacity-10 rounded">
                    <div class="h5 mb-0 text-success">500+</div>
                    <small class="text-muted">Clients</small>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 bg-warning bg-opacity-10 rounded">
                    <div class="h5 mb-0 text-warning">10+</div>
                    <small class="text-muted">Years</small>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 bg-info bg-opacity-10 rounded">
                    <div class="h5 mb-0 text-info">24/7</div>
                    <small class="text-muted">Support</small>
                </div>
            </div>
        </div>
    </div>
</div>