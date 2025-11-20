<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"
       style="margin-bottom:10px; margin-top:10px">
    <tbody>
    <tr>
        <td>
            <a href="<?php echo esc_url($link); ?>" style="background-color:#0867ec;color:white;font-weight:700;padding-top:12px;padding-bottom:12px;padding-left:24px;padding-right:24px;border-radius:4px;text-decoration-line:none;text-align:center;box-sizing:border-box;line-height:100%;text-decoration:none;display:inline-block;max-width:100%;mso-padding-alt:0px;padding:12px 24px 12px 24px" target="_blank">
                <span style="max-width:100%;display:inline-block;color:white;line-height:120%;"><?php echo wp_kses_post($btnText); ?></span>
            </a>
        </td>
    </tr>
    </tbody>
</table>
