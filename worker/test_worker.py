import unittest

from compositor import composite


class CompositeTests(unittest.TestCase):
    def test_protected_pixels_are_original_and_output_deterministic(self):
        original = bytes([10, 20, 30, 40, 50, 60])
        background = bytes([100, 110, 120, 130, 140, 150])
        result = composite(2, 1, original, background, bytes([255, 0]))
        self.assertEqual(result.status, 'ok')
        self.assertEqual(result.pixels, bytes([10, 20, 30, 130, 140, 150]))
        self.assertEqual(result, composite(2, 1, original, background, bytes([255, 0])))

    def test_uncertain_mask_requires_review_without_output(self):
        result = composite(1, 1, bytes([1, 2, 3]), bytes([4, 5, 6]), bytes([128]))
        self.assertEqual((result.status, result.pixels, result.reason),
                         ('needs_review', None, 'uncertain_mask'))

    def test_near_binary_mask_values_require_review(self):
        for value in (1, 254):
            with self.subTest(value=value):
                result = composite(1, 1, bytes([1, 2, 3]), bytes([4, 5, 6]), bytes([value]))
                self.assertEqual((result.status, result.pixels, result.reason),
                                 ('needs_review', None, 'uncertain_mask'))

    def test_dimension_mismatch_fails_closed(self):
        cases = [(2, 1, b'123', b'456789', b'\\xff\\x00'),
                 (1, 2, b'123456', b'456', b'\\xff\\x00'),
                 (1, 1, b'123', b'456', b'\\xff\\x00')]
        for args in cases:
            with self.subTest(args=args):
                result = composite(*args)
                self.assertEqual((result.status, result.pixels, result.reason),
                                 ('needs_review', None, 'invalid_dimensions'))

    def test_negative_and_float_dimensions_fail_before_processing(self):
        for width, height in ((-1, 1), (1, -1), (1.5, 1), (1, 1.5)):
            with self.subTest(width=width, height=height):
                result = composite(width, height, b'123', b'456', b'\\xff')
                self.assertEqual((result.status, result.pixels, result.reason),
                                 ('needs_review', None, 'invalid_input'))

    def test_dimensions_and_pixel_count_have_conservative_limits(self):
        for width, height in ((8193, 1), (1, 8193)):
            with self.subTest(width=width, height=height):
                result = composite(width, height, bytes(width * height * 3),
                                   bytes(width * height * 3), bytes(width * height))
                self.assertEqual((result.status, result.pixels, result.reason),
                                 ('needs_review', None, 'invalid_dimensions'))

    def test_pixel_count_limit_is_enforced(self):
        result = composite(4097, 4097, b'', b'', b'')
        self.assertEqual((result.status, result.pixels, result.reason),
                         ('needs_review', None, 'invalid_dimensions'))


    def test_invalid_input_fails_closed(self):
        cases = [(1, 1, b'123', b'456', b''),
                 (1, 1, b'12', b'456', b'\xff'),
                 (0, 1, b'', b'', b''),
                 (True, 1, b'123', b'456', b'\xff'),
                 (1, 1, b'123', b'456', None)]
        for args in cases:
            with self.subTest(args=args):
                result = composite(*args)
                self.assertEqual(result.status, 'needs_review')
                self.assertIsNone(result.pixels)


if __name__ == '__main__':
    unittest.main()
