<details data-login-location class="rounded-xl border border-green-100 bg-green-50/50 p-3 text-xs">
    <summary class="cursor-pointer font-bold text-primary">Optional location for sign-in history</summary><p class="mt-2 leading-6">You may share an approximate device location with System Admin. Your browser asks permission first. Coordinates are rounded to 0.1° before sending; an exact address is not saved. Signing in works without sharing.</p>
    <input type="hidden" name="audit_location_opt_in" value="0"><input type="hidden" name="audit_latitude" value=""><input type="hidden" name="audit_longitude" value=""><input type="hidden" name="audit_accuracy_km" value="">
    <div class="mt-3 flex flex-wrap gap-2"><button data-location-add type="button" class="rounded-lg border bg-white px-3 py-2 font-bold">Add approximate location</button><button data-location-remove type="button" hidden class="rounded-lg border bg-white px-3 py-2">Remove location</button></div><p data-location-status role="status" class="mt-2 leading-6">No location shared. It is not tracked continuously.</p>
</details>
<script src="{{ asset('js/login-location.js') }}" defer></script>
